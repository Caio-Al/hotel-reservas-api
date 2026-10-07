<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function view(User $user, Room $room): bool
    {
        return $user->canOperateHotel((int) $room->hotel_id);
    }

    public function update(User $user, Room $room): bool
    {
        return $user->canManageHotel((int) $room->hotel_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->canManageHotel((int) $room->hotel_id);
    }
}
