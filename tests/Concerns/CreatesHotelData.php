<?php

namespace Tests\Concerns;

use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;

trait CreatesHotelData
{
    protected function makeHotel(string $name = 'Hotel Teste'): Hotel
    {
        return Hotel::create(['name' => $name]);
    }

    protected function makeRoom(Hotel $hotel, string $name = 'Quarto 1'): Room
    {
        return $hotel->rooms()->create(['name' => $name]);
    }

    protected function makeReserve(Room $room, string $checkIn, string $checkOut, float $total = 100): Reserve
    {
        return Reserve::create([
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total' => $total,
        ]);
    }

    protected function userWithRole(string $role, ?Hotel $hotel = null): User
    {
        return User::factory()->create(['role' => $role, 'hotel_id' => $hotel?->id]);
    }
}
