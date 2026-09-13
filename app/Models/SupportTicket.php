<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
        'user_last_read_at' => 'datetime',
        'admin_last_read_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'general' => 'General Query',
        'subscription' => 'Subscription',
        'billing' => 'Billing & Payment',
        'account' => 'Account & Login',
        'technical' => 'Technical Issue',
        'feature' => 'Suggestion / Feature',
        'privacy' => 'Privacy & Data',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'waiting_support' => 'Waiting for Support',
        'waiting_customer' => 'Waiting for Customer',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            if (!$ticket->ticket_number) {
                do {
                    $number = 'SUP-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
                } while (static::where('ticket_number', $number)->exists());
                $ticket->ticket_number = $number;
            }
        });
    }

    public function user() { return $this->belongsTo(User::class); }
    public function household() { return $this->belongsTo(Household::class); }
    public function messages() { return $this->hasMany(SupportMessage::class)->orderBy('id'); }
    public function attachments() { return $this->hasMany(SupportAttachment::class); }

    public function unreadForUserCount(): int
    {
        return $this->messages()
            ->where('sender_type', 'admin')
            ->where('is_internal', false)
            ->when($this->user_last_read_at, fn($q) => $q->where('created_at', '>', $this->user_last_read_at))
            ->count();
    }

    public function unreadForAdminCount(): int
    {
        return $this->messages()
            ->where('sender_type', 'user')
            ->when($this->admin_last_read_at, fn($q) => $q->where('created_at', '>', $this->admin_last_read_at))
            ->count();
    }
}
