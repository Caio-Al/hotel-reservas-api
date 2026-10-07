<?php

namespace Tests\Feature;

use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesHotelData;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use CreatesHotelData;
    use RefreshDatabase;

    private Hotel $hotelA;
    private Hotel $hotelB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotelA = $this->makeHotel('Hotel A');
        $this->hotelB = $this->makeHotel('Hotel B');
    }

    private function actAs(string $role, ?Hotel $hotel = null): void
    {
        Sanctum::actingAs($this->userWithRole($role, $hotel));
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->getJson('/api/rooms')->assertUnauthorized();
    }

    public function test_admin_lista_quartos_de_todos_os_hoteis(): void
    {
        $this->makeRoom($this->hotelA, 'A1');
        $this->makeRoom($this->hotelB, 'B1');
        $this->actAs('admin');

        $this->getJson('/api/rooms')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/rooms?hotel_id='.$this->hotelB->id)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_gerente_lista_so_os_quartos_do_proprio_hotel(): void
    {
        $this->makeRoom($this->hotelA, 'A1');
        $this->makeRoom($this->hotelA, 'A2');
        $this->makeRoom($this->hotelB, 'B1');
        $this->actAs('manager', $this->hotelA);

        // Mesmo pedindo o hotel B, o filtro é ignorado para quem não é admin.
        $this->getJson('/api/rooms?hotel_id='.$this->hotelB->id)->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_usuario_sem_hotel_nao_ve_nada(): void
    {
        $this->makeRoom($this->hotelA, 'A1');
        $this->actAs('receptionist');

        $this->getJson('/api/rooms')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_gerente_cadastra_quarto_no_proprio_hotel(): void
    {
        $this->actAs('manager', $this->hotelA);

        $this->postJson('/api/rooms', ['hotel_id' => $this->hotelA->id, 'name' => 'Suíte 10'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Suíte 10')
            ->assertJsonPath('data.hotel_id', $this->hotelA->id);

        $this->assertDatabaseHas('rooms', ['hotel_id' => $this->hotelA->id, 'name' => 'Suíte 10']);
    }

    public function test_gerente_nao_cadastra_quarto_em_outro_hotel(): void
    {
        $this->actAs('manager', $this->hotelA);

        $this->postJson('/api/rooms', ['hotel_id' => $this->hotelB->id, 'name' => 'X'])->assertForbidden();
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_recepcionista_nao_cadastra_quarto(): void
    {
        $this->actAs('receptionist', $this->hotelA);

        $this->postJson('/api/rooms', ['hotel_id' => $this->hotelA->id, 'name' => 'X'])->assertForbidden();
    }

    public function test_cadastro_valida_os_dados(): void
    {
        $this->makeRoom($this->hotelA, 'A1');
        $this->actAs('admin');

        $this->postJson('/api/rooms', [])->assertUnprocessable()->assertJsonValidationErrors(['hotel_id', 'name']);
        $this->postJson('/api/rooms', ['hotel_id' => 999, 'name' => 'X'])->assertUnprocessable()->assertJsonValidationErrors(['hotel_id']);
        $this->postJson('/api/rooms', ['hotel_id' => $this->hotelA->id, 'name' => 'A1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_mesmo_nome_em_hoteis_diferentes_e_permitido(): void
    {
        $this->makeRoom($this->hotelA, 'Quarto 1');
        $this->actAs('admin');

        $this->postJson('/api/rooms', ['hotel_id' => $this->hotelB->id, 'name' => 'Quarto 1'])->assertCreated();
    }

    public function test_show_respeita_o_hotel_do_usuario(): void
    {
        $roomA = $this->makeRoom($this->hotelA, 'A1');
        $roomB = $this->makeRoom($this->hotelB, 'B1');
        $this->actAs('manager', $this->hotelA);

        $this->getJson("/api/rooms/{$roomA->id}")->assertOk()->assertJsonPath('data.name', 'A1');
        $this->getJson("/api/rooms/{$roomB->id}")->assertForbidden();
        $this->getJson('/api/rooms/9999')->assertNotFound();
    }

    public function test_atualiza_o_nome_com_put_e_patch(): void
    {
        $room = $this->makeRoom($this->hotelA, 'A1');
        $this->actAs('manager', $this->hotelA);

        $this->putJson("/api/rooms/{$room->id}", ['name' => 'Novo nome'])->assertOk()->assertJsonPath('data.name', 'Novo nome');
        $this->patchJson("/api/rooms/{$room->id}", ['name' => 'Outro nome'])->assertOk();
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'name' => 'Outro nome']);
    }

    public function test_atualizar_nao_move_o_quarto_de_hotel(): void
    {
        $room = $this->makeRoom($this->hotelA, 'A1');
        $this->actAs('admin');

        $this->patchJson("/api/rooms/{$room->id}", ['hotel_id' => $this->hotelB->id, 'name' => 'A1'])->assertOk();
        $this->assertSame($this->hotelA->id, (int) $room->fresh()->hotel_id);
    }

    public function test_remove_quarto_sem_reservas(): void
    {
        $room = $this->makeRoom($this->hotelA, 'A1');
        $this->actAs('manager', $this->hotelA);

        $this->deleteJson("/api/rooms/{$room->id}")->assertNoContent();
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_nao_remove_quarto_que_tem_reservas(): void
    {
        $room = $this->makeRoom($this->hotelA, 'A1');
        $this->makeReserve($room, '2026-12-01', '2026-12-03');
        $this->actAs('admin');

        $this->deleteJson("/api/rooms/{$room->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'O quarto possui reservas e não pode ser removido.');
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_lista_quartos_disponiveis_no_periodo(): void
    {
        $booked = $this->makeRoom($this->hotelA, 'Ocupado');
        $free = $this->makeRoom($this->hotelA, 'Livre');
        $this->makeReserve($booked, '2026-12-01', '2026-12-05');
        $this->actAs('receptionist', $this->hotelA);

        $query = fn (string $in, string $out) => "/api/rooms/available?hotel_id={$this->hotelA->id}&check_in={$in}&check_out={$out}";

        $this->getJson($query('2026-12-03', '2026-12-06'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $free->id);

        // Entrando no dia do check-out do outro hóspede, o quarto está livre.
        $this->getJson($query('2026-12-05', '2026-12-08'))->assertOk()->assertJsonCount(2, 'data');

        $this->getJson($query('2026-12-08', '2026-12-05'))->assertUnprocessable();
    }
}
