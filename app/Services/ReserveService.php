<?php

namespace App\Services;

use App\Exceptions\InvalidCouponException;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Exceptions\RoomUnavailableException;
use App\Models\Coupon;
use App\Models\Reserve;
use App\Models\User;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\ReserveRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReserveService
{
    public function __construct(
        private ReserveRepositoryInterface $reserves,
        private RoomRepositoryInterface $rooms,
        private CouponRepositoryInterface $coupons,
        private PricingService $pricing,
    ) {
    }

    /** Mesma regra de escopo dos quartos: admin vê tudo, os demais só o próprio hotel. */
    public function list(User $user, ?int $hotelFilter = null): LengthAwarePaginator
    {
        $hotelId = $user->isAdmin() ? $hotelFilter : (int) $user->hotel_id;

        return $this->reserves->paginate($hotelId);
    }

    /**
     * Cria a reserva. Tudo acontece em uma transação, com o quarto travado (lock),
     * para duas requisições simultâneas não reservarem o mesmo quarto.
     */
    public function create(array $data): Reserve
    {
        return DB::transaction(function () use ($data) {
            $room = $this->rooms->findForUpdate((int) $data['room_id']);

            if (! $room || (int) $room->hotel_id !== (int) $data['hotel_id']) {
                throw ValidationException::withMessages([
                    'room_id' => ['O quarto informado não pertence ao hotel.'],
                ]);
            }

            $this->assertDailiesMatchPeriod($data['check_in'], $data['check_out'], $data['dailies']);

            if ($this->reserves->hasOverlap($room->id, $data['check_in'], $data['check_out'])) {
                throw new RoomUnavailableException();
            }

            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null);
            $dailies = array_map(fn (array $d) => ['date' => $d['date'], 'value' => $d['value']], $data['dailies']);
            $prices = $this->pricing->calculate($dailies, $coupon);

            $payments = array_map(
                fn (array $p) => ['method' => (int) $p['method'], 'value' => $p['value']],
                $data['payments'] ?? []
            );
            $this->assertPaymentsWithinTotal($payments, $prices['total']);

            $guests = array_map(fn (array $g) => [
                'name' => $g['name'],
                'last_name' => $g['last_name'] ?? null,
                'phone' => $g['phone'] ?? null,
            ], $data['guests']);

            $reserve = $this->reserves->createWithChildren([
                'hotel_id' => $room->hotel_id,
                'room_id' => $room->id,
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'subtotal' => $prices['subtotal'],
                'discount' => $prices['discount'],
                'service_fee' => $prices['service_fee'],
                'total' => $prices['total'],
                'coupon_id' => $coupon?->id,
            ], $guests, $dailies, $payments);

            if ($coupon) {
                $this->coupons->incrementUses($coupon);
            }

            Log::info('Reserva criada', [
                'reserve_id' => $reserve->id,
                'hotel_id' => $reserve->hotel_id,
                'room_id' => $reserve->room_id,
                'total' => $prices['total'],
                'cupom' => $coupon?->code,
            ]);

            return $reserve;
        });
    }

    /** As diárias precisam cobrir exatamente cada noite entre check-in e check-out (saída exclusiva). */
    private function assertDailiesMatchPeriod(string $checkIn, string $checkOut, array $dailies): void
    {
        $expected = [];
        $cursor = Carbon::parse($checkIn)->startOfDay();
        $end = Carbon::parse($checkOut)->startOfDay();
        while ($cursor->lt($end)) {
            $expected[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $given = array_column($dailies, 'date');
        sort($expected);
        sort($given);

        if ($expected !== $given) {
            throw ValidationException::withMessages([
                'dailies' => ['As diárias devem cobrir cada noite entre check_in e check_out, sem repetir datas.'],
            ]);
        }
    }

    private function resolveCoupon(?string $code): ?Coupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $coupon = $this->coupons->findByCodeForUpdate($code);
        $today = now()->toDateString();

        if (! $coupon || ! $coupon->active) {
            throw new InvalidCouponException('Cupom inválido ou inativo.');
        }
        if ($coupon->valid_from && $today < $coupon->valid_from->toDateString()) {
            throw new InvalidCouponException('Cupom ainda não está válido.');
        }
        if ($coupon->valid_until && $today > $coupon->valid_until->toDateString()) {
            throw new InvalidCouponException('Cupom expirado.');
        }
        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            throw new InvalidCouponException('Cupom esgotado.');
        }

        return $coupon;
    }

    private function assertPaymentsWithinTotal(array $payments, string $total): void
    {
        $paid = array_sum(array_map(fn (array $p) => (int) round(((float) $p['value']) * 100), $payments));

        if ($paid > (int) round(((float) $total) * 100)) {
            throw new PaymentExceedsBalanceException('Os pagamentos informados excedem o total da reserva.');
        }
    }
}
