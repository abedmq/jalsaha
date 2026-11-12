<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'is_enabled' => 'boolean'];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(SubscriptionService::class, 'subscription_service_id');
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_enabled) {
            return 'disabled';
        }
        if ($this->starts_at->isAfter(today())) {
            return 'scheduled';
        }

        return $this->ends_at->isBefore(today()) ? 'expired' : 'active';
    }
}
