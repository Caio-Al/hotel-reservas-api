<?php

namespace App\Repositories\Eloquent;

use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RoomRepository extends EloquentRepository implements RoomRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Room());
    }

    public function paginate(?int $hotelId, int $perPage = 15): LengthAwarePaginator
    {
        return Room::query()
            ->when($hotelId !== null, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findForUpdate(int $id): ?Room
    {
        return Room::query()->lockForUpdate()->find($id);
    }

    public function hasReserves(Room $room): bool
    {
        return $room->reserves()->exists();
    }

    public function availableBetween(int $hotelId, string $checkIn, string $checkOut): Collection
    {
        return Room::query()
            ->where('hotel_id', $hotelId)
            ->whereDoesntHave('reserves', function ($query) use ($checkIn, $checkOut) {
                $query->where('check_in', '<', $checkOut)->where('check_out', '>', $checkIn);
            })
            ->orderBy('id')
            ->get();
    }
}
