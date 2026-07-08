<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HouseholdMember extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    /**
     * Get the household this membership belongs to.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the user this membership belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if member is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
