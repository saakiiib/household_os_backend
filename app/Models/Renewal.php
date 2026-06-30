<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Renewal extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'renewal_date'      => 'date',
        'cost'              => 'float',
        'reminder_sent_90d' => 'boolean',
        'reminder_sent_30d' => 'boolean',
        'reminder_sent_7d'  => 'boolean',
        'reminder_sent_due' => 'boolean',
    ];

    /**
     * Get the household this renewal belongs to.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the user who created the renewal.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user responsible for this renewal.
     */
    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get the renewal history entries.
     */
    public function history()
    {
        return $this->hasMany(RenewalHistory::class);
    }

    /**
     * Get the number of days remaining until renewal_date.
     * Negative means overdue.
     */
    public function getDaysRemainingAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->renewal_date->startOfDay(), false);
    }

    /**
     * Get an urgency label: overdue / urgent (≤7d) / soon (≤30d) / upcoming (≤90d) / normal.
     */
    public function getUrgencyAttribute(): string
    {
        $days = $this->days_remaining;
        if ($days < 0)   return 'overdue';
        if ($days <= 7)  return 'urgent';
        if ($days <= 30) return 'soon';
        if ($days <= 90) return 'upcoming';
        return 'normal';
    }

    /**
     * Calculate next renewal date based on frequency.
     */
    public function nextRenewalDate(): ?Carbon
    {
        return match ($this->frequency) {
            'annual'     => $this->renewal_date->copy()->addYear(),
            'bi-annual'  => $this->renewal_date->copy()->addMonths(6),
            'quarterly'  => $this->renewal_date->copy()->addMonths(3),
            'monthly'    => $this->renewal_date->copy()->addMonth(),
            'one-time'   => null,
            default      => null,
        };
    }
}
