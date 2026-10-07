<?php

namespace App\Services;

use App\Exceptions\RoomHasReservesException;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class RoomService
{
    public function __construct(private RoomRepositoryInterface $rooms)
    {
    }

    public function list(User $user, ?int $hotelFilter = null): LengthAwarePaginator
    {
        $hotelId = $user->isAdmin() ? $hotelFilter : (int) $user->hotel_id;

        return $this->rooms->paginate($hotelId);
    }

    public function create(array $data): Room
    {
        /** @var Room $room */
        $room = $this->rooms->create($data);
        Log::info('Quarto criado', ['room_id' => $room->id, 'hotel_id' => $room->hotel_id]);

        return $room;
    }

    public function update(Room $room, array $data): Room
    {
        /** @var Room $room */
        $room = $this->rooms->update($room, $data);
        Log::info('Quarto atualizado', ['room_id' => $room->id]);

        return $room;
    }

    public function delete(Room $room): void
    {
        if ($this->rooms->hasReserves($room)) {
            throw new RoomHasReservesException();
        }

        $this->rooms->delete($room);
        Log::info('Quarto removido', ['room_id' => $room->id, 'hotel_id' => $room->hotel_id]);
    }

    public function available(int $hotelId, string $checkIn, string $checkOut): Collection
    {
        return $this->rooms->availableBetween($hotelId, $checkIn, $checkOut);
    }
}
