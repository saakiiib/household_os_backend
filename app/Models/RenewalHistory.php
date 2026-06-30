<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalHistory extends Model
{
    use HasFactory;

    protected $table = 'renewal_history';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'previous_date' => 'date',
        'new_date'      => 'date',
        'cost'          => 'float',
        'created_at'    => 'datetime',
    ];

    /**
     * Get the renewal this history entry belongs to.
     */
    public function renewal()
    {
        return $this->belongsTo(Renewal::class);
    }

    /**
     * Get the user who renewed.
     */
    public function renewedBy()
    {
        return $this->belongsTo(User::class, 'renewed_by_user_id');
    }
}
