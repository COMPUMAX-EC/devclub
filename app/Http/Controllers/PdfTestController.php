<?php

namespace App\Http\Controllers; // arriba del archivo, junto a los otros "use"


use App\Services\PdfService;

class PdfTestController extends Controller
{

	/**
	 * /test/pdf → ver el PDF generado por HTML2PDF.
	 */
	public function generatePdf(PdfService $pdf)
	{
		// Crear el PDF con HTML2PDF
		$options = [
			'orientation' => 'P',
			'format'	  => 'LETTER',
			'margin'	  => [10, 10, 10, 10]
		];

		$template_path = resource_path('views/pdf/test.blade.php');

		return response(
					$pdf->makePdfFromFile($template_path, [], $options)
				)->header('Content-Type', 'application/pdf');
	}
}
