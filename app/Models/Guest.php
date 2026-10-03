<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $fillable = ['reserve_id', 'name', 'last_name', 'phone'];   

    public function reserve(): BelongsTo
    {
        return $this->belongsTo(Reserve::class); 
    }
}
