<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
        });
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('zone_id')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
        });
        Schema::create('subscription_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('sender_domains');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('address')->unique();
            $table->boolean('is_active')->default(true);
            $table->uuid('active_lease_id')->nullable();
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_service_id')->constrained()->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->index();
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->string('sender');
            $table->string('subject', 500);
            $table->longText('body');
            $table->text('otp')->nullable();
            $table->uuid('claimed_lease_id')->nullable()->index();
            $table->timestamp('received_at')->index();
            $table->timestamps();
        });
        Schema::create('mailbox_leases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('access_grant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('mail_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['mailbox_leases', 'mail_messages', 'access_grants', 'subscriptions', 'mailboxes', 'subscription_services', 'domains'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_admin', 'is_active', 'permissions']));
    }
};
