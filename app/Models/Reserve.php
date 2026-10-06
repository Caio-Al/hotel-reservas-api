<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserve extends Model
{
    protected $fillable = ['id', 'hotel_id', 'room_id', 'check_in', 'check_out', 'total'];   

        protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'total' => 'decimal:2',
        ];
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class); 
    }

    public function room()
    {
        return $this->belongsTo(Room::class); 
    }

    public function guests()
    {
        return $this->hasMany(Guest::class); 
    }

    public function dailies()
    {
        return $this->hasMany(Daily::class); 
    }

    public function payments()
    {
        return $this->hasMany(Payment::class); 
    }

}
