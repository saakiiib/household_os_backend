<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Promotion extends Model { protected $guarded=[]; protected $casts=['eligible_plans'=>'array','starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean']; }
