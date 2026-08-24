<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPlan extends Model
{
    protected $fillable = [
        'plan_id',
        'provider',
        'product_id',
        'billing_period',
        'level',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
