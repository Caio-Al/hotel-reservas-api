<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Hotel;
use App\Repositories\Contracts\HotelRepositoryInterface;
use App\Repositories\Contracts\ReserveRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesHotelData;
use Tests\TestCase;

class ReserveRepositoryTest extends TestCase
{
    use CreatesHotelData;
    use RefreshDatabase;

    private ReserveRepositoryInterface $reserves;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reserves = app(ReserveRepositoryInterface::class);
    }

    public function test_detecta_sobreposicao_de_periodos(): void
    {
        $room = $this->makeRoom($this->makeHotel());
        $this->makeReserve($room, '2026-10-01', '2026-10-05');

        $this->assertTrue($this->reserves->hasOverlap($room->id, '2026-10-03', '2026-10-07'), 'começa dentro');
        $this->assertTrue($this->reserves->hasOverlap($room->id, '2026-09-28', '2026-10-02'), 'termina dentro');
        $this->assertTrue($this->reserves->hasOverlap($room->id, '2026-10-02', '2026-10-03'), 'contido');
        $this->assertTrue($this->reserves->hasOverlap($room->id, '2026-09-30', '2026-10-10'), 'envolve');
    }

    public function test_periodos_encostados_nao_conflitam(): void
    {
        $room = $this->makeRoom($this->makeHotel());
        $this->makeReserve($room, '2026-10-01', '2026-10-05');

        $this->assertFalse($this->reserves->hasOverlap($room->id, '2026-10-05', '2026-10-08'), 'entra no dia do check-out');
        $this->assertFalse($this->reserves->hasOverlap($room->id, '2026-09-28', '2026-10-01'), 'sai no dia do check-in');
    }

    public function test_sobreposicao_considera_apenas_o_mesmo_quarto(): void
    {
        $hotel = $this->makeHotel();
        $roomA = $this->makeRoom($hotel, 'A');
        $roomB = $this->makeRoom($hotel, 'B');
        $this->makeReserve($roomA, '2026-10-01', '2026-10-05');

        $this->assertFalse($this->reserves->hasOverlap($roomB->id, '2026-10-02', '2026-10-04'));
    }

    public function test_sync_children_substitui_os_filhos_sem_duplicar(): void
    {
        $room = $this->makeRoom($this->makeHotel());
        $reserve = $this->makeReserve($room, '2026-10-01', '2026-10-03', 200);

        $guests = [['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '1']];
        $dailies = [['date' => '2026-10-01', 'value' => 100], ['date' => '2026-10-02', 'value' => 100]];
        $payments = [['method' => 1, 'value' => 50]];

        $this->reserves->syncChildren($reserve, $guests, $dailies, $payments);
        $this->reserves->syncChildren($reserve, $guests, $dailies, $payments);

        $this->assertSame(1, $reserve->guests()->count());
        $this->assertSame(2, $reserve->dailies()->count());
        $this->assertSame(1, $reserve->payments()->count());
        $this->assertSame(5000, $this->reserves->paidCents($reserve));
        $this->assertSame(1, Guest::count());
    }

    public function test_update_or_create_respeita_o_id_informado_e_nao_duplica(): void
    {
        $hotels = app(HotelRepositoryInterface::class);

        $hotels->updateOrCreate(['id' => 7], ['name' => 'Primeiro nome']);
        $hotels->updateOrCreate(['id' => 7], ['name' => 'Nome atualizado']);

        $this->assertSame(1, Hotel::count());
        $this->assertSame('Nome atualizado', Hotel::find(7)->name);
    }
}
