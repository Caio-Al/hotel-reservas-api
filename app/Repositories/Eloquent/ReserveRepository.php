<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Models\Reserve;
use App\Repositories\Contracts\ReserveRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReserveRepository extends EloquentRepository implements ReserveRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Reserve());
    }

    public function paginate(?int $hotelId, int $perPage = 15): LengthAwarePaginator
    {
        return Reserve::query()
            ->with(['guests', 'dailies', 'payments', 'coupon'])
            ->when($hotelId !== null, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUpdate(int $id): ?Reserve
    {
        return Reserve::query()->lockForUpdate()->find($id);
    }

    public function hasOverlap(int $roomId, string $checkIn, string $checkOut): bool
    {
        // Dois períodos [a, b) e [c, d) se sobrepõem quando a < d e b > c.
        // Check-out no mesmo dia do check-in seguinte não conflita.
        return Reserve::query()
            ->where('room_id', $roomId)
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
    }

    public function createWithChildren(array $data, array $guests, array $dailies, array $payments): Reserve
    {
        $reserve = Reserve::query()->create($data);
        $this->fillChildren($reserve, $guests, $dailies, $payments);

        return $reserve->load(['guests', 'dailies', 'payments', 'coupon']);
    }

    public function syncChildren(Reserve $reserve, array $guests, array $dailies, array $payments): void
    {
        $reserve->guests()->delete();
        $reserve->dailies()->delete();
        $reserve->payments()->delete();

        $this->fillChildren($reserve, $guests, $dailies, $payments);
    }

    public function addPayment(Reserve $reserve, array $data): Payment
    {
        return $reserve->payments()->create($data);
    }

    public function paidCents(Reserve $reserve): int
    {
        return (int) round(((float) $reserve->payments()->sum('value')) * 100);
    }

    private function fillChildren(Reserve $reserve, array $guests, array $dailies, array $payments): void
    {
        if ($guests) {
            $reserve->guests()->createMany($guests);
        }
        if ($dailies) {
            $reserve->dailies()->createMany($dailies);
        }
        if ($payments) {
            $reserve->payments()->createMany($payments);
        }
    }
}
