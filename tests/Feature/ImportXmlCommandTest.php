<?php

namespace Tests\Feature;

use App\Models\Daily;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ImportXmlCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_os_xmls_do_desafio(): void
    {
        $this->artisan('hotel:import-xml')->assertSuccessful();

        $this->assertSame(3, Hotel::count());
        $this->assertSame(6, Room::count());
        $this->assertSame(6, Reserve::count());
        $this->assertSame(6, Guest::count());
        $this->assertSame(18, Daily::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame('3400.00', number_format((float) Reserve::sum('total'), 2, '.', ''));
    }

    public function test_mantem_os_ids_e_as_relacoes_do_xml(): void
    {
        $this->artisan('hotel:import-xml')->assertSuccessful();

        $reserve = Reserve::find(6);
        $this->assertSame(2, (int) $reserve->hotel_id);
        $this->assertSame(3, (int) $reserve->room_id);
        $this->assertSame('Hotel Foco Beach', $reserve->hotel->name);
        $this->assertSame(0, Reserve::find(2)->payments()->count(), 'reserva 2 não tem pagamentos');
    }

    public function test_rodar_duas_vezes_nao_duplica_nada(): void
    {
        $this->artisan('hotel:import-xml')->assertSuccessful();
        $this->artisan('hotel:import-xml')->assertSuccessful();

        $this->assertSame(3, Hotel::count());
        $this->assertSame(6, Room::count());
        $this->assertSame(6, Reserve::count());
        $this->assertSame(6, Guest::count());
        $this->assertSame(18, Daily::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_diaria_fora_do_periodo_gera_aviso_mas_importa(): void
    {
        Log::spy();

        $this->artisan('hotel:import-xml')
            ->expectsOutputToContain('Avisos: 1')
            ->assertSuccessful();

        Log::shouldHaveReceived('warning')->once();
        $this->assertSame(3, Reserve::find(6)->dailies()->count());
    }

    public function test_pasta_inexistente_falha_sem_gravar_nada(): void
    {
        $this->artisan('hotel:import-xml', ['--path' => 'pasta/que/nao/existe'])->assertFailed();

        $this->assertSame(0, Hotel::count());
    }
}
