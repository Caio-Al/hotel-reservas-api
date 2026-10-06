<?php

namespace App\Providers;

use App\Repositories\Contracts\HotelRepositoryInterface;
use App\Repositories\Contracts\ReserveRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Eloquent\HotelRepository;
use App\Repositories\Eloquent\ReserveRepository;
use App\Repositories\Eloquent\RoomRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        HotelRepositoryInterface::class => HotelRepository::class,
        RoomRepositoryInterface::class => RoomRepository::class,
        ReserveRepositoryInterface::class => ReserveRepository::class,
    ];
}
