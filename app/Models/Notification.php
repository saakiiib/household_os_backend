<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Notifications only use created_at, no updated_at
    const UPDATED_AT = null;

    protected $casts = [
        'data'     => 'array',
        'channels' => 'array',
        'sent_at'  => 'datetime',
        'read_at'  => 'datetime',
    ];

    /**
     * Get the household associated with this notification.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the user who receives this notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
