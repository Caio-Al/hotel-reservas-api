<?php

namespace App\Console\Commands;

use App\Services\XmlImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportXml extends Command
{
    protected $signature = 'hotel:import-xml {--path=database/xml : Pasta (relativa à raiz do projeto) com hotels.xml, rooms.xml e reserves.xml}';

    protected $description = 'Importa hotels.xml, rooms.xml e reserves.xml para o banco de dados';

    public function handle(XmlImportService $service): int
    {
        $path = (string) $this->option('path');
        $directory = str_starts_with($path, '/') ? $path : base_path($path);

        try {
            $stats = $service->import($directory);
        } catch (Throwable $e) {
            Log::error('Falha na importação XML', ['erro' => $e->getMessage(), 'pasta' => $directory]);
            $this->error('Falha na importação: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Hotéis: {$stats['hotels']}");
        $this->info("Quartos: {$stats['rooms']}");
        $this->info("Reservas: {$stats['reserves']}");
        $this->info("Avisos: {$stats['warnings']}");

        return self::SUCCESS;
    }
}
