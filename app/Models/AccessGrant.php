<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessGrant extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function usable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture() && $this->subscription->status === 'active';
    }
}
