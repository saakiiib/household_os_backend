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
        'subscriber_user_id',
        'subscription_plan_id',
        'status',
        'plan_status',
        'paid_plan',
        'billing_period',
        'trial_started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'expires_at',
        'cancelled_at',
        'payment_method',
        'provider',
        'product_id',
        'original_transaction_id',
        'latest_transaction_id',
        'environment',
        'auto_renew',
        'app_account_token',
        'grace_period_expires_at',
        'expired_at',
        'revoked_at',
        'last_verified_at',
        'stripe_subscription_id',
        'stripe_customer_id',
        'paypal_subscription_id',
        'apple_product_id',
        'apple_original_transaction_id',
        'apple_receipt_data',
        'google_product_id',
        'google_order_id',
        'google_purchase_token',
        'metadata',
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'grace_period_expires_at' => 'datetime',
        'expired_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'auto_renew' => 'boolean',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subscriber_user_id');
    }

    public function isActive(): bool
    {
        // A trial is active only while status == trial AND trial_ends_at is in
        // the future. An expired trial naturally falls back to Lifetime Free.
        if ($this->status === 'trial') {
            return $this->trial_ends_at !== null
                && now()->isBefore($this->trial_ends_at);
        }
        // A cancelled subscription retains access until the end of the paid
        // period (current_period_end), matching the cancel confirmation message.
        if ($this->status === 'cancelled') {
            $cutoff = $this->expires_at ?? $this->current_period_end;
            return $cutoff !== null && now()->isBefore($cutoff);
        }
        // billing_retry = Apple is re-trying payment while expiresDate keeps
        // extending — the customer retains access (command.txt §31).
        if (!in_array($this->status, ['active', 'grace_period', 'billing_retry'])) {
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
        // Use the authoritative expires_at for expiry check. For Apple, only
        // apply on-demand (Apple re-queries live). For Google/Stripe/PayPal,
        // the expires_at is set by webhooks or verify flows and is authoritative.
        if ($this->expires_at && now()->isAfter($this->expires_at)) {
            // Auto-renewing grace period: if auto_renew is on and the
            // subscription only recently expired (within 5 minutes), keep it
            // active. This prevents a false "expired/free" flash during sandbox
            // renewals (Google sandbox compresses months to minutes) and
            // production renewal processing delays.
            if ($this->auto_renew && now()->diffInSeconds($this->expires_at) <= 300) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Check if a downgrade is allowed (after current period ends)
     */
    public function canDowngradeNow(): bool
    {
        if ($this->status !== 'active' && $this->status !== 'grace_period') {
            return true; // Already expired, can downgrade anytime
        }
        // Can only downgrade after current_period_end has passed
        if ($this->current_period_end && now()->isBefore($this->current_period_end)) {
            return false;
        }
        return true;
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
