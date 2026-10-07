<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;

interface CouponRepositoryInterface extends RepositoryInterface
{
    /** Busca o cupom pelo código travando a linha (usar dentro de transação). */
    public function findByCodeForUpdate(string $code): ?Coupon;

    public function incrementUses(Coupon $coupon): void;
}
