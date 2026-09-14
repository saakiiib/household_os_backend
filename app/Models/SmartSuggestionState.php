<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmartSuggestionState extends Model
{
    protected $fillable = [
        'household_id',
        'user_id',
        'suggestion_key',
        'status',
    ];
}
