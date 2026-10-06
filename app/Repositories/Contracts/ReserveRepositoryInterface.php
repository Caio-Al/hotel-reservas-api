<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;
use App\Models\Reserve;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReserveRepositoryInterface extends RepositoryInterface
{
    public function paginate(?int $hotelId, int $perPage = 15): LengthAwarePaginator;

    /** Busca a reserva travando a linha (usar dentro de transação). */
    public function findForUpdate(int $id): ?Reserve;

    /** Existe reserva no quarto que se sobrepõe ao período [checkIn, checkOut)? */
    public function hasOverlap(int $roomId, string $checkIn, string $checkOut): bool;

    public function createWithChildren(array $data, array $guests, array $dailies, array $payments): Reserve;

    /** Substitui hóspedes, diárias e pagamentos da reserva (importação repetível). */
    public function syncChildren(Reserve $reserve, array $guests, array $dailies, array $payments): void;

    public function addPayment(Reserve $reserve, array $data): Payment;

    /** Total já pago, em centavos. */
    public function paidCents(Reserve $reserve): int;
}
