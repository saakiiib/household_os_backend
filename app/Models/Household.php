<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    use HasFactory;

    protected $guarded = [];

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
                    ->withPivot('role', 'status')
                    ->withTimestamps();
    }
}
