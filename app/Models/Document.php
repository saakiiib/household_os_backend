<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function files()
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function allowedMembers()
    {
        return $this->belongsToMany(User::class, 'document_allowed_members', 'document_id', 'user_id');
    }

    /**
     * Check if a user can view this document.
     * Rule:
     * - Creator can always view their own document.
     * - Any other user MUST be an active member of this document's household.
     * - If active member, access is granted if visibility is 'all'
     *   OR if visibility is 'specific' and the user is in allowedMembers.
     */
    public function canUserView(int $userId): bool
    {
        if ($this->created_by_user_id === $userId) {
            return true;
        }

        $isActiveMember = HouseholdMember::where('household_id', $this->household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (!$isActiveMember) {
            return false;
        }

        if ($this->visibility === 'all') {
            return true;
        }

        if ($this->visibility === 'specific') {
            return $this->allowedMembers()->where('users.id', $userId)->exists();
        }

        return false;
    }

    /**
     * Check if a user can manage (update, delete, upload/delete files) this document.
     * Rule: Only the document creator can manage it.
     */
    public function canUserManage(int $userId): bool
    {
        return $this->created_by_user_id === $userId;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) return null;
        return (int) now()->diffInDays($this->due_date, false);
    }
}
