<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Renewal extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected $appends = ['has_document'];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function parent()
    {
        return $this->belongsTo(Renewal::class, 'parent_renewal_id');
    }

    public function children()
    {
        return $this->hasMany(Renewal::class, 'parent_renewal_id');
    }

    public function vehicleServices()
    {
        return $this->hasMany(RenewalVehicleService::class);
    }

    /**
     * Check if a user can view this renewal.
     * Any active household member can view household renewals.
     */
    public function canUserView(int $userId): bool
    {
        return HouseholdMember::where('household_id', $this->household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check if a user can act on this renewal (complete, renew, start).
     * User must be an active household member AND (creator OR assigned user).
     */
    public function canUserAct(int $userId): bool
    {
        if (!HouseholdMember::where('household_id', $this->household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists()) {
            return false;
        }

        return $this->created_by_user_id === $userId
            || $this->assigned_user_id === $userId;
    }

    /**
     * Check if a user can manage this renewal (edit details, change assignee, delete).
     * User must be an active household member AND the creator.
     */
    public function canUserManage(int $userId): bool
    {
        if (!HouseholdMember::where('household_id', $this->household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists()) {
            return false;
        }

        return $this->created_by_user_id === $userId;
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date || $this->status === 'completed') return false;
        // Compare only dates, not time - overdue means due date was BEFORE today
        return $this->due_date->toDateString() < now()->toDateString();
    }

    public function getNeedsActionAttribute(): bool
    {
        if (!$this->due_date || $this->status === 'completed') return false;
        // Needs action if overdue OR due today
        return $this->due_date->toDateString() <= now()->toDateString();
    }

    public function getIsRenewableAttribute(): bool
    {
        return $this->status === 'completed' || $this->is_overdue;
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) return null;
        // Compare only dates - returns 0 if due today, negative if overdue
        $today = now()->startOfDay();
        $due = $this->due_date->startOfDay();
        return (int) $today->diffInDays($due, false);
    }

    public function getHasDocumentAttribute(): bool
    {
        return !empty($this->document_file_path);
    }

    public function documentFullPath(): ?string
    {
        if (empty($this->document_file_path)) {
            return null;
        }

        $path = public_path(ltrim($this->document_file_path, '/'));

        return is_file($path) ? $path : null;
    }

    protected static function booted(): void
    {
        static::deleted(function (Renewal $renewal) {
            $fullPath = $renewal->documentFullPath();

            if ($fullPath) {
                @unlink($fullPath);
            }
        });
    }
}
