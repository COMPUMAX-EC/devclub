<?php

namespace App\Console\Commands;

use App\Services\PdfService;
use Illuminate\Console\Command;

class RefreshCaches extends Command
{

	/**
	 * El nombre para invocar el comando
	 *
	 * @var string
	 */
	protected $signature = 'app:refresh';

	/**
	 * Descripción visible en `php artisan list`
	 *
	 * @var string
	 */
	protected $description = 'Limpia y regenera las caches de Laravel para despliegue';

	/**
	 * Ejecutar el comando
	 */
	public function handle(PdfService $pdf)
	{
		$this->info('== Comenzando refresh de caches ==');

		$this->call('config:clear');
		$this->call('optimize:clear');
		$this->call('route:clear');
		$this->call('view:cache');
		$this->call('cache:clear');
		$this->call('permission:cache-reset');
        $this->call('storage:link', ['--force' => true]);

		$this->info('== Regenerando fuentes PDF ==');
		$pdf->registerFonts(true); // fuerza la regeneración

		$this->info('== Refresh completado ==');
		return 0;
	}
}
