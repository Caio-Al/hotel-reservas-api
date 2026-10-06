<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    private function user(string $role, ?int $hotelId): User
    {
        return (new User())->forceFill(['role' => $role, 'hotel_id' => $hotelId]);
    }

    public function test_admin_gerencia_e_opera_qualquer_hotel(): void
    {
        $admin = $this->user('admin', null);

        $this->assertTrue($admin->canManageHotel(1));
        $this->assertTrue($admin->canOperateHotel(2));
    }

    public function test_gerente_so_gerencia_o_proprio_hotel(): void
    {
        $manager = $this->user('manager', 1);

        $this->assertTrue($manager->canManageHotel(1));
        $this->assertFalse($manager->canManageHotel(2));
        $this->assertTrue($manager->canOperateHotel(1));
        $this->assertFalse($manager->canOperateHotel(2));
    }

    public function test_recepcionista_opera_mas_nao_gerencia(): void
    {
        $receptionist = $this->user('receptionist', 1);

        $this->assertFalse($receptionist->canManageHotel(1));
        $this->assertTrue($receptionist->canOperateHotel(1));
        $this->assertFalse($receptionist->canOperateHotel(2));
    }

    public function test_usuario_sem_hotel_e_sem_papel_admin_nao_acessa_nada(): void
    {
        $user = $this->user('receptionist', null);

        $this->assertFalse($user->canOperateHotel(1));
        $this->assertFalse($user->canManageHotel(1));
    }
}
