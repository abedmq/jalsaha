<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mailbox extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'locked_until' => 'datetime'];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(MailboxLease::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function scopeVisibleTo($query, User $user)
    {
        return $query->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id));
    }
}
