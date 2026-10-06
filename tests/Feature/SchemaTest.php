<?php

namespace Tests\Feature;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesHotelData;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use CreatesHotelData;
    use RefreshDatabase;

    public function test_tabelas_e_colunas_do_sistema_existem(): void
    {
        $this->assertTrue(Schema::hasTable('coupons'));
        $this->assertTrue(Schema::hasColumns('users', ['role', 'hotel_id']));
        $this->assertTrue(Schema::hasColumns('reserves', ['subtotal', 'discount', 'service_fee', 'coupon_id']));
    }

    public function test_reserva_guarda_valores_de_preco_e_cupom(): void
    {
        $room = $this->makeRoom($this->makeHotel());
        $coupon = Coupon::create(['code' => 'TESTE', 'type' => 'fixed', 'value' => 10]);

        $reserve = $this->makeReserve($room, '2026-11-01', '2026-11-03', 198);
        $reserve->update(['subtotal' => 200, 'discount' => 20, 'service_fee' => 18, 'coupon_id' => $coupon->id]);

        $reserve->refresh();
        $this->assertSame('200.00', $reserve->subtotal);
        $this->assertSame('18.00', $reserve->service_fee);
        $this->assertSame($coupon->id, $reserve->coupon->id);
    }

    public function test_novo_usuario_nasce_com_o_menor_privilegio(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->assertSame('receptionist', $user->fresh()->role);
    }
}
