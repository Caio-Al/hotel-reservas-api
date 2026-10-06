<?php

namespace Tests\Feature;

use App\Repositories\Contracts\HotelRepositoryInterface;
use App\Repositories\Contracts\ReserveRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Eloquent\HotelRepository;
use App\Repositories\Eloquent\ReserveRepository;
use App\Repositories\Eloquent\RoomRepository;
use Tests\TestCase;

class RepositoryBindingTest extends TestCase
{
    public function test_interfaces_resolvem_para_as_implementacoes_eloquent(): void
    {
        $this->assertInstanceOf(HotelRepository::class, app(HotelRepositoryInterface::class));
        $this->assertInstanceOf(RoomRepository::class, app(RoomRepositoryInterface::class));
        $this->assertInstanceOf(ReserveRepository::class, app(ReserveRepositoryInterface::class));
    }
}
