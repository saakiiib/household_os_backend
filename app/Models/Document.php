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
     */
    public function canUserView(int $userId): bool
    {
        if ($this->visibility === 'all') {
            return true;
        }

        return $this->allowedMembers()->where('user_id', $userId)->exists()
            || $this->created_by_user_id === $userId;
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
