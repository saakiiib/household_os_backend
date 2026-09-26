<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialIdentity extends Model
{
    protected $guarded = [];
    protected $hidden = ['provider_subject'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
