<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\MailMessage;
use App\Models\Subscription;
use App\Models\SubscriptionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Mailbox $mailbox;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->owner = User::factory()->create(['is_admin' => true]);
        $domain = Domain::create(['name' => 'example.com', 'is_active' => true]);
        $service = SubscriptionService::create(['name' => 'Example', 'sender_domains' => ['trusted.example'], 'is_active' => true]);
        $this->mailbox = Mailbox::create(['domain_id' => $domain->id, 'user_id' => $this->owner->id, 'address' => 'customer@example.com']);
        $this->subscription = Subscription::create(['mailbox_id' => $this->mailbox->id, 'subscription_service_id' => $service->id, 'starts_at' => today()->subDay(), 'ends_at' => today()->addDays(30)]);
        config(['jalsaha.webhook_secret' => str_repeat('a', 64)]);
    }

    private function link(): array
    {
        return $this->actingAs($this->owner)->postJson('/subscriptions/'.$this->subscription->id.'/link')->assertOk()->assertJsonPath('active', true)->json();
    }

    private function poll(array $lease)
    {
        return $this->getJson('/leases/'.$lease['lease_id'], ['X-Lease-Token' => $lease['lease_token']]);
    }

    private function message(array $attributes = []): MailMessage
    {
        return MailMessage::create(array_merge(['mailbox_id' => $this->mailbox->id, 'subscription_service_id' => $this->subscription->subscription_service_id, 'fingerprint' => hash('sha256', random_bytes(32)), 'sender' => 'login@trusted.example', 'subject' => 'Verification code', 'body' => 'Your code is 123456', 'otp' => '123456', 'received_at' => now()], $attributes));
    }

    private function inbound(array $overrides = [])
    {
        $data = array_merge(['recipient' => $this->mailbox->address, 'sender' => 'login@trusted.example', 'subject' => 'Your verification code', 'text' => 'Your code is 654321', 'fingerprint' => str_repeat('b', 64), 'source_received_at' => now()->toIso8601String()], $overrides);
        $json = json_encode($data);
        $timestamp = (string) time();

        return $this->call('POST', '/api/inbound-email', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_JALSAHA_TIMESTAMP' => $timestamp, 'HTTP_X_JALSAHA_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$json, config('jalsaha.webhook_secret'))], $json);
    }

    public function test_exclusive_link_and_ten_minute_expiry(): void
    {
        $lease = $this->link();
        $this->postJson('/subscriptions/'.$this->subscription->id.'/link')->assertStatus(409);
        $this->travel(601)->seconds();
        $this->poll($lease)->assertOk()->assertJsonPath('active', false);
        $next = $this->link();
        $this->assertNotSame($lease['lease_id'], $next['lease_id']);
    }

    public function test_recent_code_is_delivered_once_and_retained_after_release(): void
    {
        $message = $this->message(['received_at' => now()->subSeconds(30)]);
        $lease = $this->link();
        $this->assertSame('123456', $lease['code']);
        $this->assertSame($lease['lease_id'], $message->fresh()->claimed_lease_id);
        $this->travel(61)->seconds();
        $this->poll($lease)->assertJsonPath('active', false)->assertJsonPath('code', '123456');
        $next = $this->link();
        $this->assertNull($next['code']);
        $this->message(['otp' => '999999']);
        $this->poll($next)->assertJsonPath('code', '999999');
        $this->poll($lease)->assertJsonPath('code', '123456')->assertJsonPath('active', false);
        $this->assertSame($next['lease_id'], $this->mailbox->fresh()->active_lease_id);
    }

    public function test_waiting_lease_receives_new_code_and_gets_sixty_seconds(): void
    {
        $lease = $this->link();
        $this->assertNull($lease['code']);
        $this->travel(100)->seconds();
        $this->message();
        $this->poll($lease)->assertJsonPath('code', '123456')->assertJsonPath('active', true);
        $this->travel(59)->seconds();
        $this->poll($lease)->assertJsonPath('active', true);
        $this->travel(2)->seconds();
        $this->poll($lease)->assertJsonPath('active', false);
    }

    public function test_old_and_wrong_service_codes_are_not_disclosed(): void
    {
        $this->message(['received_at' => now()->subSeconds(61)]);
        $this->message(['subscription_service_id' => null]);
        $lease = $this->link();
        $this->assertNull($lease['code']);
    }

    public function test_wrong_lease_token_is_rejected(): void
    {
        $lease = $this->link();
        $this->getJson('/leases/'.$lease['lease_id'], ['X-Lease-Token' => 'wrong'])->assertForbidden();
    }

    public function test_scope_and_permissions_block_other_users(): void
    {
        $other = User::factory()->create(['permissions' => ['codes.view', 'mailboxes.manage', 'mail.read']]);
        $this->actingAs($other)->get('/mailboxes/'.$this->mailbox->id)->assertNotFound();
        $this->postJson('/subscriptions/'.$this->subscription->id.'/link')->assertNotFound();
        $this->get('/manage/users')->assertForbidden();
        $this->get('/manage/mailboxes')->assertOk()->assertDontSee($this->mailbox->address);
    }

    public function test_signed_webhook_is_idempotent_and_encrypts_content(): void
    {
        $this->inbound()->assertOk();
        $this->inbound()->assertOk();
        $this->assertDatabaseCount('mail_messages', 1);
        $message = MailMessage::first();
        $this->assertSame('654321', $message->otp);
        $this->assertStringNotContainsString('654321', $message->getRawOriginal('otp'));
        $this->assertStringNotContainsString('Your code', $message->getRawOriginal('body'));
    }

    public function test_unsigned_webhook_and_spoofed_service_domain(): void
    {
        $this->postJson('/api/inbound-email', [])->assertUnauthorized();
        $this->inbound(['sender' => 'login@trusted.example.evil.com'])->assertOk();
        $this->assertNull(MailMessage::first()->otp);
    }

    public function test_delayed_queue_message_does_not_become_a_fresh_code(): void
    {
        $this->inbound(['source_received_at' => now()->subMinutes(5)->toIso8601String()])->assertOk();
        $this->assertNull($this->link()['code']);
    }

    public function test_public_customer_link_is_scoped_and_revocable(): void
    {
        $token = str_repeat('g', 64);
        $grant = AccessGrant::create(['mailbox_id' => $this->mailbox->id, 'subscription_id' => $this->subscription->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        $this->postJson('/receive/'.$token.'/link', ['email' => 'other@example.com'])->assertUnprocessable();
        $lease = $this->postJson('/receive/'.$token.'/link', ['email' => $this->mailbox->address])->assertOk()->json();
        $this->actingAs($this->owner)->delete('/grants/'.$grant->id)->assertRedirect();
        $this->poll($lease)->assertForbidden();
        $this->assertNull($this->mailbox->fresh()->active_lease_id);
        $this->get('/receive/'.$token)->assertForbidden();
    }

    public function test_disabled_users_and_expired_subscriptions_cannot_link(): void
    {
        $this->subscription->update(['ends_at' => today()->subDay()]);
        $this->actingAs($this->owner)->postJson('/subscriptions/'.$this->subscription->id.'/link')->assertUnprocessable();
        $this->owner->update(['is_active' => false]);
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_management_pages_render_and_mailbox_generation_works(): void
    {
        $this->actingAs($this->owner);
        foreach (['domains', 'services', 'mailboxes', 'subscriptions', 'users'] as $resource) {
            $this->get('/manage/'.$resource)->assertOk();
            $this->get('/manage/'.$resource.'/create')->assertOk();
        }
        foreach (['/dashboard', '/mailboxes/'.$this->mailbox->id, '/inbox', '/subscriptions/'.$this->subscription->id.'/receive'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->post('/manage/mailboxes', ['domain_id' => $this->mailbox->domain_id, 'local_part' => '', 'count' => 3, 'user_id' => $this->owner->id, 'is_active' => 1])->assertRedirect('/manage/mailboxes');
        $this->assertDatabaseCount('mailboxes', 4);
    }

    public function test_no_default_password_is_seeded(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_subject_and_body_are_escaped_and_hidden_without_permission(): void
    {
        $message = $this->message(['subject' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>']);
        $this->actingAs($this->owner)->get('/messages/'.$message->id)->assertOk()->assertDontSee('<img src=x', false)->assertSee('&lt;img', false);
        $this->owner->update(['is_admin' => false, 'permissions' => ['codes.view']]);
        $this->get('/messages/'.$message->id)->assertForbidden();
        $this->get('/inbox')->assertOk()->assertDontSee('alert(1)');
    }

    public function test_user_manager_cannot_escalate_privileges(): void
    {
        $user = User::factory()->create(['permissions' => ['users.manage']]);
        $this->actingAs($user)->postJson('/manage/users', ['name' => 'Other', 'email' => 'other@example.org', 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123', 'is_admin' => 1, 'is_active' => 1])->assertForbidden();
    }
}
