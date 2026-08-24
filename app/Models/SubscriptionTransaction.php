<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionTransaction extends Model
{
    protected $fillable = [
        'subscription_id',
        'transaction_id',
        'original_transaction_id',
        'product_id',
        'transaction_reason',
        'environment',
        'purchase_date',
        'expires_date',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'expires_date' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
