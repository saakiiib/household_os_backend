<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function getIsVehicleAttribute(): bool
    {
        return $this->slug === 'vehicle';
    }

    public function scopeForDocuments($query)
    {
        return $query->where('type', 'document');
    }

    public function scopeForRenewals($query)
    {
        return $query->where('type', 'renewal');
    }
}
