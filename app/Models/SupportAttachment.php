<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportAttachment extends Model
{
    protected $guarded = [];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function message() { return $this->belongsTo(SupportMessage::class, 'support_message_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
}
