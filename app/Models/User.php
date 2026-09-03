<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function households()
    {
        return $this->belongsToMany(Household::class, 'household_members')
                    ->using(\App\Models\HouseholdMember::class)
                    ->withPivot('role', 'status', 'joined_at')
                    ->withTimestamps();
    }

    public function householdMemberships()
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /**
     * Get all registered device tokens for this user (multi-device FCM).
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Get subscriptions this user purchased (for payment history).
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get payments this user made (for payment history).
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the user's active household (first active membership).
     */
    public function activeHousehold()
    {
        return $this->belongsToMany(Household::class, 'household_members')
                    ->using(\App\Models\HouseholdMember::class)
                    ->wherePivot('status', 'active')
                    ->withPivot('role', 'status', 'joined_at')
                    ->withTimestamps()
                    ->first();
    }

    /**
     * Check if the user's household has an active subscription.
     * Subscription is household-based, not user-based.
     */
    public function hasActiveSubscription(): bool
    {
        $household = $this->activeHousehold();
        if (!$household) {
            return false;
        }

        $subscription = $household->subscription;
        if (!$subscription) {
            return false;
        }

        return $subscription->isActive();
    }

    /**
     * Get the household's subscription (for display purposes).
     */
    public function householdSubscription()
    {
        $household = $this->activeHousehold();
        if (!$household) {
            return null;
        }

        $subscription = $household->subscription;
        if (!$subscription) {
            return null;
        }

        // Auto-expire if period ended and not recently re-verified.
        // Avoids immediately marking sandbox purchases as expired before the
        // user can see the active state.
        if ($subscription->status !== 'expired' && $subscription->isExpired()) {
            $recentlyVerified = $subscription->last_verified_at
                && now()->diffInMinutes($subscription->last_verified_at) < 10;

            if (!$recentlyVerified) {
                $subscription->update(['status' => 'expired']);
            }
        }

        return $subscription;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
