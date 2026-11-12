<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model
{
    protected $guarded = [];

    protected $hidden = ['body', 'otp'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'otp' => 'encrypted', 'received_at' => 'datetime'];
    }

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function service()
    {
        return $this->belongsTo(SubscriptionService::class, 'subscription_service_id');
    }
}
