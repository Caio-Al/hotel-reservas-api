<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Daily extends Model
{
    protected $table = 'dailies';
    protected $fillable = ['reserve_id', 'date', 'value'];   

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    public function reserve(): BelongsTo
    {
        return $this->belongsTo(Reserve::class); 
    }
}
