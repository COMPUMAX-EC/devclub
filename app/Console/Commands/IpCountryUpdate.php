<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\IpCountry\IpCountryService;

class IpCountryUpdate extends Command
{
    protected $signature = 'ip-country:update {--force : Fuerza la descarga aunque no esté vencida}';
    protected $description = 'Actualiza la base MMDB local para resolver países por IP';

    public function handle(IpCountryService $svc): int
    {
        $force = (bool) $this->option('force');

        if ($force) {
            $svc->forceUpdateDatabase();
        } else {
            // “ensure” normal: solo baja si falta o venció
            $svc->resolveIso2ByIp('8.8.8.8'); // dispara ensureFreshDatabase() de forma segura
        }

        $this->info('OK');
        return self::SUCCESS;
    }
}
