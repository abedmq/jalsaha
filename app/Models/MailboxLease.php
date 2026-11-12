<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailboxLease extends Model
{
    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'delivered_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function grant()
    {
        return $this->belongsTo(AccessGrant::class, 'access_grant_id');
    }

    public function message()
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
