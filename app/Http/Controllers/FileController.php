<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{

	/**
	 * Descarga/visualización pública/no firmada usando UUID.
	 * Ruta: GET /files/{uuid}
	 */
	public function showByUuid(string $uuid)
	{
		$file = File::where('uuid', $uuid)->firstOrFail();

		return $this->serveFile($file);
	}

	/**
	 * Descarga/visualización temporal usando ID + firma.
	 * Ruta: GET /files/temp/{file}?signature=...
	 */
	public function showTemporary(Request $request, File $file)
	{
		if (!$request->hasValidSignature())
		{
			abort(403, 'Link expirado o inválido.');
		}

		return $this->serveFile($file);
	}

	/**
	 * Lógica común de entrega de archivo.
	 * - Para imágenes y PDFs: inline (se ven en el navegador).
	 * - Para el resto: descarga forzada.
	 */
	protected function serveFile(File $file)
	{
		$disk = $file->disk;
		$path = $file->path;

		if (!Storage::disk($disk)->exists($path))
		{
			abort(404, 'Archivo no encontrado.');
		}

		$filename = $file->original_name ?: basename($path);

		// Intentamos obtener el mime type
		$mime = $file->mime_type ?: Storage::disk($disk)->mimeType($path);

		if ($this->shouldDisplayInline($mime))
		{
			// Mostrar en el navegador (imagen / PDF)
			$headers = [
				'Content-Type'		  => $mime,
				'Content-Disposition' => 'inline; filename="' . $filename . '"',
			];

			return Storage::disk($disk)->response($path, $filename, $headers);
		}

		// Resto de archivos: descarga forzada
		return Storage::disk($disk)->download($path, $filename);
	}

	/**
	 * Determina si el archivo debe mostrarse inline.
	 */
	protected function shouldDisplayInline(?string $mime): bool
	{
		if (!$mime)
		{
			return false;
		}

		$mime = strtolower($mime);

		if (str_starts_with($mime, 'image/'))
		{
			return true;
		}

		if ($mime === 'application/pdf')
		{
			return true;
		}

		return false;
	}
}
