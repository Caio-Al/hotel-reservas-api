<?php

namespace App\Repositories\Contracts;

use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface RoomRepositoryInterface extends RepositoryInterface
{
    /** Lista quartos paginados. $hotelId null = todos os hotéis. */
    public function paginate(?int $hotelId, int $perPage = 15): LengthAwarePaginator;

    /** Busca o quarto travando a linha (usar dentro de transação). */
    public function findForUpdate(int $id): ?Room;

    public function hasReserves(Room $room): bool;

    /** Quartos do hotel sem reserva sobreposta ao período [checkIn, checkOut). */
    public function availableBetween(int $hotelId, string $checkIn, string $checkOut): Collection;
}
