<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_encrypted'       => 'boolean',
        'is_sensitive'       => 'boolean',
        'shared_with_roles'  => 'array',
        'shared_with_users'  => 'array',
        'expiry_date'        => 'date',
        'download_count'     => 'integer',
    ];

    /**
     * Get the household that owns the document.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Get access log entries for this document.
     */
    public function accessLogs()
    {
        return $this->hasMany(DocumentAccess::class);
    }
}
