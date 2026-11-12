<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionService extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sender_domains' => 'array', 'is_active' => 'boolean'];
    }
}
