<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'apple_product_id',
        'google_product_id',
        'description',
        'monthly_price',
        'annual_price',
        'features',
        'is_popular',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function productPlans()
    {
        return $this->hasMany(\App\Models\ProductPlan::class, 'plan_id');
    }
}
