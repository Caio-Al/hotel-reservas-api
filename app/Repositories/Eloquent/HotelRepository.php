<?php

namespace App\Repositories\Eloquent;

use App\Models\Hotel;
use App\Repositories\Contracts\HotelRepositoryInterface;

class HotelRepository extends EloquentRepository implements HotelRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Hotel());
    }
}
