<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\Mailbox;
use App\Models\MailboxLease;
use App\Models\MailMessage;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaseService
{
    public function acquire(Subscription $subscription, ?User $user = null, ?AccessGrant $grant = null): array
    {
        return DB::transaction(function () use ($subscription, $user, $grant) {
            $mailbox = Mailbox::whereKey($subscription->mailbox_id)->lockForUpdate()->firstOrFail();
            $subscription->refresh();
            abort_unless($mailbox->is_active && $mailbox->domain->is_active && $subscription->service->is_active && $subscription->status === 'active', 422, 'الإيميل أو الاشتراك غير فعال');
            if ($grant) {
                abort_unless($grant->fresh()->usable(), 403);
            }
            $id = (string) Str::uuid();
            $until = now()->addSeconds(config('jalsaha.lease_seconds'));
            // Atomic conditional update protects SQLite too; InnoDB also serializes the mailbox row.
            $updated = Mailbox::whereKey($mailbox->id)->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<=', now()))
                ->update(['active_lease_id' => $id, 'locked_until' => $until]);
            abort_unless($updated, 409, 'هذا الإيميل مرتبط حاليًا، حاول لاحقًا');
            MailboxLease::where('mailbox_id', $mailbox->id)->whereNull('released_at')->update(['released_at' => now(), 'release_reason' => 'expired']);
            $token = Str::random(64);
            $lease = MailboxLease::create([
                'id' => $id, 'mailbox_id' => $mailbox->id, 'subscription_id' => $subscription->id,
                'user_id' => $user?->id, 'access_grant_id' => $grant?->id,
                'token_hash' => hash('sha256', $token), 'expires_at' => $until,
            ]);

            return ['lease_id' => $id, 'lease_token' => $token] + $this->state($lease, $mailbox->refresh());
        }, 3);
    }

    public function poll(string $id, string $token): array
    {
        $lease = MailboxLease::findOrFail($id);
        abort_unless(hash_equals($lease->token_hash, hash('sha256', $token)), 403);

        $result = DB::transaction(function () use ($lease) {
            $mailbox = Mailbox::whereKey($lease->mailbox_id)->lockForUpdate()->firstOrFail();
            $lease->refresh();
            $authorized = $lease->access_grant_id
                ? ($lease->grant && $lease->grant->usable())
                : ($lease->user && $lease->user->permits('codes.view') && ($lease->user->is_admin || $mailbox->user_id === $lease->user_id));
            $authorized = $authorized && $mailbox->is_active && $mailbox->domain->is_active && $lease->subscription->status === 'active' && $lease->subscription->service->is_active;
            if (! $authorized) {
                $this->release($lease, $mailbox, 'revoked');

                return ['forbidden' => true];
            }

            return $this->state($lease, $mailbox);
        }, 3);

        abort_if(isset($result['forbidden']), 403, 'انتهت صلاحية الوصول');

        return $result;
    }

    private function state(MailboxLease $lease, Mailbox $mailbox): array
    {
        if (! $lease->released_at && ($lease->expires_at->lte(now()) || $mailbox->active_lease_id !== $lease->id)) {
            $this->release($lease, $mailbox, $lease->delivered_at ? 'delivered' : 'expired');
        }
        if (! $lease->released_at && ! $lease->delivered_at) {
            $message = MailMessage::where('mailbox_id', $mailbox->id)
                ->where('subscription_service_id', $lease->subscription->subscription_service_id)
                ->whereNotNull('otp')->whereNull('claimed_lease_id')
                ->where('received_at', '>=', now()->subSeconds(config('jalsaha.recent_code_seconds')))
                ->latest('id')->lockForUpdate()->first();
            if ($message) {
                $message->update(['claimed_lease_id' => $lease->id]);
                $until = now()->addSeconds(config('jalsaha.release_after_delivery'))->min($lease->expires_at);
                $lease->update(['mail_message_id' => $message->id, 'delivered_at' => now(), 'expires_at' => $until]);
                $mailbox->update(['locked_until' => $until]);
            }
        }

        return [
            'active' => ! $lease->released_at,
            'expires_at' => $lease->expires_at->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'code' => $lease->message?->otp,
            'received_at' => $lease->message?->received_at->toIso8601String(),
        ];
    }

    public function release(MailboxLease $lease, Mailbox $mailbox, string $reason): void
    {
        if (! $lease->released_at) {
            $lease->update(['released_at' => now(), 'release_reason' => $reason]);
        }
        Mailbox::whereKey($mailbox->id)->where('active_lease_id', $lease->id)->update(['active_lease_id' => null, 'locked_until' => null]);
    }
}
