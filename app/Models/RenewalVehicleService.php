<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalVehicleService extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'service_date' => 'date',
        'service_amount' => 'decimal:2',
    ];

    public function renewal()
    {
        return $this->belongsTo(Renewal::class);
    }
}
