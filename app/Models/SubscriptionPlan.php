<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'annual_price',
        'features',
        'is_popular',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'is_popular' => 'boolean',
    ];
}
