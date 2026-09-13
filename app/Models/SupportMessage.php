<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $guarded = [];
    protected $casts = ['is_internal' => 'boolean'];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
    public function attachments() { return $this->hasMany(SupportAttachment::class, 'support_message_id'); }
}
