<?php

namespace App\Repositories\Eloquent;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;

class CouponRepository extends EloquentRepository implements CouponRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Coupon());
    }

    public function findByCodeForUpdate(string $code): ?Coupon
    {
        return Coupon::query()->where('code', mb_strtoupper(trim($code)))->lockForUpdate()->first();
    }

    public function incrementUses(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }
}
