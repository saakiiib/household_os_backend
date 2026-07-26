<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Household extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Auto-generate a unique 8-char invite code before creating the household.
     */
    protected static function booted(): void
    {
        static::creating(function (Household $household) {
            if (empty($household->invite_code)) {
                do {
                    $code = strtoupper(Str::random(8));
                } while (static::where('invite_code', $code)->exists());

                $household->invite_code = $code;
            }
        });
    }

    /**
     * Get the user who created the household (admin).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get all members associated with this household.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'household_members')
                    ->withPivot('role', 'status', 'joined_at')
                    ->wherePivot('status', 'active')
                    ->withTimestamps();
    }

    /**
     * Get all HouseholdMember pivot records for this household.
     */
    public function householdMembers()
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /**
     * Get all invitations for this household.
     */
    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Get the household's subscription (one per household).
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Get all payments for this household.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
