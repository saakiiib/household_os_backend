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
        'notify_days_before' => 'integer',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(Renewal::class, 'parent_renewal_id');
    }

    public function children()
    {
        return $this->hasMany(Renewal::class, 'parent_renewal_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function getIsRenewableAttribute(): bool
    {
        return $this->status === 'completed' || $this->is_overdue;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'completed';
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) return null;
        return (int) now()->diffInDays($this->due_date, false);
    }
}
