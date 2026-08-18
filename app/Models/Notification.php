<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data'     => 'array',
        'read_at'  => 'datetime',
    ];

    public const PRIORITIES = [
        'critical' => 'critical',  // overdue, subscription expiry, security
        'high'     => 'high',      // due today, day-before
        'normal'   => 'normal',    // upcoming, daily digests
        'low'      => 'low',       // tips, weekly summaries
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}
