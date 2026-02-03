<?php

namespace App\Console\Commands;

use App\Services\PdfService;
use Illuminate\Console\Command;

class MigrateAll extends Command
{

	/**
	 * El nombre para invocar el comando
	 *
	 * @var string
	 */
	protected $signature = 'app:migrateAll';

	/**
	 * Descripción visible en `php artisan list`
	 *
	 * @var string
	 */
	protected $description = 'Ejecuta las migraciones sin preguntar';

	/**
	 * Ejecutar el comando
	 */
	public function handle(PdfService $pdf)
	{
       $this->info('== Ejecutando migraciones ==');
        $this->call('migrate', ['--force' => true]);

		return 0;
	}
}
