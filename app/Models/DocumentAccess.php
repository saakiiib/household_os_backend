<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentAccess extends Model
{
    use HasFactory;

    protected $table = 'document_access';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    /**
     * Get the document that was accessed.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user who accessed the document.
     */
    public function accessedBy()
    {
        return $this->belongsTo(User::class, 'accessed_by_user_id');
    }
}
