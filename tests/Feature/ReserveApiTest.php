<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesHotelData;
use Tests\TestCase;

class ReserveApiTest extends TestCase
{
    use CreatesHotelData;
    use RefreshDatabase;

    private Hotel $hotel;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.service_fee_percent' => 10.0]);

        $this->hotel = $this->makeHotel();
        $this->room = $this->makeRoom($this->hotel);
    }

    private function actAs(string $role, ?Hotel $hotel = null): void
    {
        Sanctum::actingAs($this->userWithRole($role, $hotel ?? $this->hotel));
    }

    private function day(int $daysFromNow): string
    {
        return now()->addDays($daysFromNow)->toDateString();
    }

    /** Reserva de 2 noites (a partir de daqui a 10 dias) com 2 diárias de 100. */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'check_in' => $this->day(10),
            'check_out' => $this->day(12),
            'guests' => [['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '5577999999999']],
            'dailies' => [
                ['date' => $this->day(10), 'value' => 100],
                ['date' => $this->day(11), 'value' => 100],
            ],
        ], $overrides);
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->postJson('/api/reserves', $this->payload())->assertUnauthorized();
    }

    public function test_cria_reserva_calculando_total_e_taxa_no_servidor(): void
    {
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.service_fee', '20.00')
            ->assertJsonPath('data.total', '220.00')
            ->assertJsonPath('data.balance', '220.00')
            ->assertJsonCount(1, 'data.guests')
            ->assertJsonCount(2, 'data.dailies');

        $this->assertDatabaseCount('reserves', 1);
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseCount('dailies', 2);
    }

    public function test_cliente_nao_consegue_impor_o_total(): void
    {
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload(['total' => 1, 'service_fee' => 0]))
            ->assertCreated()
            ->assertJsonPath('data.total', '220.00');
    }

    public function test_cupom_percentual_e_aplicado_e_contado(): void
    {
        $coupon = Coupon::create(['code' => 'BEMVINDO10', 'type' => 'percent', 'value' => 10]);
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload(['coupon_code' => 'bemvindo10']))
            ->assertCreated()
            ->assertJsonPath('data.discount', '20.00')
            ->assertJsonPath('data.service_fee', '18.00')
            ->assertJsonPath('data.total', '198.00')
            ->assertJsonPath('data.coupon', 'BEMVINDO10');

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_cupom_invalido_expirado_ou_esgotado_e_recusado_sem_criar_reserva(): void
    {
        Coupon::create(['code' => 'VELHO', 'type' => 'percent', 'value' => 10, 'valid_until' => now()->subDay()->toDateString()]);
        Coupon::create(['code' => 'ESGOTADO', 'type' => 'percent', 'value' => 10, 'max_uses' => 1, 'used_count' => 1]);
        Coupon::create(['code' => 'INATIVO', 'type' => 'percent', 'value' => 10, 'active' => false]);
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload(['coupon_code' => 'NAOEXISTE']))
            ->assertUnprocessable()->assertJsonPath('message', 'Cupom inválido ou inativo.');
        $this->postJson('/api/reserves', $this->payload(['coupon_code' => 'INATIVO']))
            ->assertUnprocessable()->assertJsonPath('message', 'Cupom inválido ou inativo.');
        $this->postJson('/api/reserves', $this->payload(['coupon_code' => 'VELHO']))
            ->assertUnprocessable()->assertJsonPath('message', 'Cupom expirado.');
        $this->postJson('/api/reserves', $this->payload(['coupon_code' => 'ESGOTADO']))
            ->assertUnprocessable()->assertJsonPath('message', 'Cupom esgotado.');

        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_quarto_ocupado_no_periodo_retorna_409(): void
    {
        $this->actAs('receptionist');
        $this->postJson('/api/reserves', $this->payload())->assertCreated();

        $this->postJson('/api/reserves', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'O quarto já está reservado no período informado.');

        $this->assertDatabaseCount('reserves', 1);
    }

    public function test_reservas_encostadas_no_mesmo_quarto_sao_permitidas(): void
    {
        $this->actAs('receptionist');
        $this->postJson('/api/reserves', $this->payload())->assertCreated();

        // Entra no dia em que o outro hóspede sai.
        $this->postJson('/api/reserves', $this->payload([
            'check_in' => $this->day(12),
            'check_out' => $this->day(14),
            'dailies' => [
                ['date' => $this->day(12), 'value' => 100],
                ['date' => $this->day(13), 'value' => 100],
            ],
        ]))->assertCreated();

        $this->assertDatabaseCount('reserves', 2);
    }

    public function test_diarias_precisam_cobrir_exatamente_as_noites(): void
    {
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload([
            'dailies' => [['date' => $this->day(10), 'value' => 100]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['dailies']);

        $this->postJson('/api/reserves', $this->payload([
            'dailies' => [
                ['date' => $this->day(10), 'value' => 100],
                ['date' => $this->day(10), 'value' => 100],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['dailies']);

        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_quarto_de_outro_hotel_e_recusado(): void
    {
        $other = $this->makeRoom($this->makeHotel('Outro hotel'), 'Quarto do outro hotel');
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload(['room_id' => $other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['room_id']);
    }

    public function test_funcionario_de_outro_hotel_nao_cria_reserva(): void
    {
        $otherHotel = $this->makeHotel('Outro hotel');
        Sanctum::actingAs($this->userWithRole('manager', $otherHotel));

        $this->postJson('/api/reserves', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_valida_datas_e_limite_de_estadia(): void
    {
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload(['check_in' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors(['check_in']);

        $this->postJson('/api/reserves', $this->payload(['check_out' => $this->day(5)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['check_out']);

        $this->postJson('/api/reserves', $this->payload(['check_out' => $this->day(10 + 61)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['check_out']);

        $this->postJson('/api/reserves', [])->assertForbidden();
        Sanctum::actingAs($this->userWithRole('admin'));
        $this->postJson('/api/reserves', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hotel_id', 'room_id', 'check_in', 'check_out', 'guests', 'dailies']);
    }

    public function test_pagamentos_na_criacao_nao_podem_passar_do_total(): void
    {
        $this->actAs('receptionist');

        $this->postJson('/api/reserves', $this->payload(['payments' => [['method' => 1, 'value' => 500]]]))
            ->assertUnprocessable();
        $this->assertDatabaseCount('reserves', 0);

        $this->postJson('/api/reserves', $this->payload(['payments' => [['method' => 1, 'value' => 100]]]))
            ->assertCreated()
            ->assertJsonPath('data.paid', '100.00')
            ->assertJsonPath('data.balance', '120.00');
    }

    public function test_registra_pagamentos_e_controla_o_saldo(): void
    {
        $this->actAs('receptionist');
        $id = $this->postJson('/api/reserves', $this->payload())->json('data.id');

        $this->postJson("/api/reserves/{$id}/payments", ['method' => 2, 'value' => 100])
            ->assertCreated()->assertJsonPath('data.value', '100.00');

        $this->getJson("/api/reserves/{$id}")
            ->assertOk()
            ->assertJsonPath('data.paid', '100.00')
            ->assertJsonPath('data.balance', '120.00');

        $this->postJson("/api/reserves/{$id}/payments", ['method' => 2, 'value' => 200])
            ->assertUnprocessable();

        $this->postJson("/api/reserves/{$id}/payments", ['method' => 2, 'value' => 120])->assertCreated();
        $this->getJson("/api/reserves/{$id}/payments")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/reserves/{$id}")->assertJsonPath('data.balance', '0.00');
    }

    public function test_pagamento_valida_os_dados(): void
    {
        $this->actAs('receptionist');
        $id = $this->postJson('/api/reserves', $this->payload())->json('data.id');

        $this->postJson("/api/reserves/{$id}/payments", ['method' => 1, 'value' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['value']);
        $this->postJson("/api/reserves/{$id}/payments", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['method', 'value']);
    }

    public function test_listagem_e_detalhe_respeitam_o_hotel_do_usuario(): void
    {
        $otherHotel = $this->makeHotel('Outro hotel');
        $otherReserve = $this->makeReserve($this->makeRoom($otherHotel), '2026-12-01', '2026-12-03');
        $this->actAs('receptionist');
        $this->postJson('/api/reserves', $this->payload())->assertCreated();

        $this->getJson('/api/reserves')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/reserves/{$otherReserve->id}")->assertForbidden();
        $this->postJson("/api/reserves/{$otherReserve->id}/payments", ['method' => 1, 'value' => 10])->assertForbidden();
        $this->getJson('/api/reserves/9999')->assertNotFound();
    }

    public function test_admin_ve_reservas_de_todos_os_hoteis(): void
    {
        $otherHotel = $this->makeHotel('Outro hotel');
        $this->makeReserve($this->makeRoom($otherHotel), '2026-12-01', '2026-12-03');
        $this->makeReserve($this->room, '2026-12-01', '2026-12-03');
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/reserves')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_detalhe_de_reserva_inexistente_retorna_404_ate_para_admin(): void
    {
        $reserve = $this->makeReserve($this->room, '2026-12-01', '2026-12-03');
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/reserves/9999')->assertNotFound();
        $this->getJson("/api/reserves/{$reserve->id}")->assertOk()->assertJsonPath('data.id', $reserve->id);
    }
}
