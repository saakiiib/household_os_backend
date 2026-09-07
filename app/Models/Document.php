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
     * - User MUST be an active member of this document's household.
     * - If active member, access is granted if:
     *   - Creator of the document, OR
     *   - Visibility is 'all', OR
     *   - Visibility is 'specific' and user is in allowedMembers.
     */
    public function canUserView(int $userId): bool
    {
        // First: user must be an active member of the household
        $isActiveMember = HouseholdMember::where('household_id', $this->household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (!$isActiveMember) {
            return false;
        }

        // Then: check permissions for active members
        if ($this->created_by_user_id === $userId) {
            return true;
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
     * Rule: User must be an active household member AND the document creator.
     */
    public function canUserManage(int $userId): bool
    {
        // First: user must be an active member of the household
        $isActiveMember = HouseholdMember::where('household_id', $this->household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (!$isActiveMember) {
            return false;
        }

        // Then: only the creator can manage
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
