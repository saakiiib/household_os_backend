<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    const GRACE_PERIOD_DAYS = 3;

    protected $fillable = [
        'user_id',
        'household_id',
        'subscription_plan_id',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'expires_at',
        'cancelled_at',
        'payment_method',
        'stripe_subscription_id',
        'stripe_customer_id',
        'paypal_subscription_id',
        'apple_product_id',
        'apple_original_transaction_id',
        'apple_receipt_data',
        'google_product_id',
        'google_order_id',
        'metadata',
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        if ($this->status === 'trial') {
            return true;
        }
        if ($this->status !== 'active' && $this->status !== 'grace_period') {
            return false;
        }
        return !$this->isFullyExpired();
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function isInGracePeriod(): bool
    {
        if ($this->status === 'grace_period') {
            return true;
        }
        if ($this->status === 'active' && $this->current_period_end && $this->expires_at) {
            return now()->isAfter($this->current_period_end) && now()->isBefore($this->expires_at);
        }
        return false;
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->isFullyExpired();
    }

    public function isFullyExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }
        if ($this->expires_at && now()->isAfter($this->expires_at)) {
            return true;
        }
        return false;
    }

    public function daysRemaining(): int
    {
        if (!$this->expires_at) {
            if (!$this->current_period_end) {
                return 0;
            }
            $target = $this->current_period_end;
        } else {
            $target = $this->expires_at;
        }

        $now = now();
        if ($now->isAfter($target)) {
            return 0;
        }
        return (int) $now->diffInDays($target);
    }

    public function daysUntilRenewal(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }
        $now = now();
        if ($now->isAfter($this->current_period_end)) {
            return 0;
        }
        return (int) $now->diffInDays($this->current_period_end);
    }

    public function graceDaysRemaining(): int
    {
        if (!$this->isInGracePeriod() || !$this->expires_at) {
            return 0;
        }
        $now = now();
        if ($now->isAfter($this->expires_at)) {
            return 0;
        }
        return (int) $now->diffInDays($this->expires_at);
    }

    /**
     * Mark as grace period when period ends.
     */
    public function moveToGracePeriod(): void
    {
        $this->update([
            'status' => 'grace_period',
            'expires_at' => $this->current_period_end->copy()->addDays(self::GRACE_PERIOD_DAYS),
        ]);
    }

    /**
     * Mark as fully expired after grace period ends.
     */
    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}
