<?php

namespace App\Services;

use App\Repositories\Contracts\HotelRepositoryInterface;
use App\Repositories\Contracts\ReserveRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;

/**
 * Lê hotels.xml, rooms.xml e reserves.xml e grava no banco.
 * Repetível: rodar várias vezes não duplica registros.
 */
class XmlImportService
{
    private int $warnings = 0;

    public function __construct(
        private HotelRepositoryInterface $hotels,
        private RoomRepositoryInterface $rooms,
        private ReserveRepositoryInterface $reserves,
    ) {
    }

    /** @return array{hotels:int, rooms:int, reserves:int, warnings:int} */
    public function import(string $directory): array
    {
        $hotelsXml = $this->load($directory.'/hotels.xml');
        $roomsXml = $this->load($directory.'/rooms.xml');
        $reservesXml = $this->load($directory.'/reserves.xml');

        $this->warnings = 0;
        $stats = ['hotels' => 0, 'rooms' => 0, 'reserves' => 0];

        DB::transaction(function () use ($hotelsXml, $roomsXml, $reservesXml, &$stats) {
            foreach ($hotelsXml->Hotel as $hotel) {
                $this->hotels->updateOrCreate(['id' => (int) $hotel['id']], [
                    'name' => (string) $hotel->Name,
                ]);
                $stats['hotels']++;
            }

            foreach ($roomsXml->Room as $room) {
                $this->rooms->updateOrCreate(['id' => (int) $room['id']], [
                    'hotel_id' => (int) $room['hotelCode'],
                    'name' => (string) $room->Name,
                ]);
                $stats['rooms']++;
            }

            foreach ($reservesXml->Reserve as $node) {
                $this->importReserve($node);
                $stats['reserves']++;
            }
        });

        $stats['warnings'] = $this->warnings;
        Log::info('Importação XML concluída', $stats);

        return $stats;
    }

    private function importReserve(SimpleXMLElement $node): void
    {
        $id = (int) $node['id'];
        $checkIn = (string) $node->CheckIn;
        $checkOut = (string) $node->CheckOut;
        $total = (string) $node->Total;

        $guests = [];
        foreach ($node->Guests->Guest ?? [] as $guest) {
            $guests[] = [
                'name' => (string) $guest->Name,
                'last_name' => (string) $guest->LastName,
                'phone' => (string) $guest->Phone,
            ];
        }

        $dailies = [];
        foreach ($node->Dailies->Daily ?? [] as $daily) {
            $dailies[] = ['date' => (string) $daily->Date, 'value' => (string) $daily->Value];
        }

        $payments = [];
        if (isset($node->Payments)) {
            foreach ($node->Payments->Payment as $payment) {
                $payments[] = ['method' => (int) $payment->Method, 'value' => (string) $payment->Value];
            }
        }

        $this->checkConsistency($id, $checkIn, $checkOut, $total, $dailies, $payments);

        $reserve = $this->reserves->updateOrCreate(['id' => $id], [
            'hotel_id' => (int) $node['hotelCode'],
            'room_id' => (int) $node['roomCode'],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'subtotal' => $total,
            'discount' => 0,
            'service_fee' => 0,
            'total' => $total,
        ]);

        $this->reserves->syncChildren($reserve, $guests, $dailies, $payments);
    }

    private function checkConsistency(int $id, string $checkIn, string $checkOut, string $total, array $dailies, array $payments): void
    {
        foreach ($dailies as $daily) {
            if ($daily['date'] < $checkIn || $daily['date'] >= $checkOut) {
                $this->warn('Importação XML: diária fora do período da reserva', [
                    'reserve_id' => $id, 'date' => $daily['date'], 'check_in' => $checkIn, 'check_out' => $checkOut,
                ]);
            }
        }

        $dailiesCents = $this->sumCents($dailies);
        if ($dailiesCents !== $this->cents($total)) {
            $this->warn('Importação XML: total difere da soma das diárias', [
                'reserve_id' => $id, 'total' => $total, 'soma_diarias' => $dailiesCents / 100,
            ]);
        }

        if ($this->sumCents($payments) > $this->cents($total)) {
            $this->warn('Importação XML: pagamentos maiores que o total', [
                'reserve_id' => $id, 'total' => $total,
            ]);
        }
    }

    private function warn(string $message, array $context): void
    {
        $this->warnings++;
        Log::warning($message, $context);
    }

    private function sumCents(array $items): int
    {
        return array_sum(array_map(fn (array $item) => $this->cents($item['value']), $items));
    }

    private function cents(string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function load(string $file): SimpleXMLElement
    {
        if (! is_file($file)) {
            throw new RuntimeException("Arquivo não encontrado: {$file}");
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            $detail = $errors ? trim($errors[0]->message) : 'erro desconhecido';
            throw new RuntimeException("XML inválido em {$file}: {$detail}");
        }

        return $xml;
    }
}
