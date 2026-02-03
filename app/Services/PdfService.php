<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Oriceon\PdfMerger\Facades\PdfMerger;
use TCPDF_FONTS;
use Spipu\Html2Pdf\Html2Pdf;

class PdfService
{

	protected string $sourceFonts;
	protected string $tcpdfFonts;
	protected array $registeredFamilies = [];

	public function __construct()
	{
		// Carpeta donde guardas tus .ttf
		$this->sourceFonts = resource_path('views/pdf/assets/fonts');

		// Carpeta interna de TCPDF donde deben ir las fuentes convertidas
		$this->tcpdfFonts = base_path('vendor/tecnickcom/tcpdf/fonts') . '/';
	}

	/**
	 * Registra todas las fuentes TTF en TCPDF (convirtiéndolas si es necesario).
	 *
	 * @param bool $force  Si es true, borra cualquier conversión previa y las regenera.
	 * @return array       Mapa de familias/variantes registradas.
	 */
	public function registerFonts(bool $force = false): array
	{
		if (!is_dir($this->sourceFonts))
		{
			return [];
		}

		if (!is_writable($this->tcpdfFonts))
		{
			throw new \RuntimeException("La carpeta de TCPDF no es escribible: {$this->tcpdfFonts}");
		}

		if (!class_exists(TCPDF_FONTS::class))
		{
			throw new \RuntimeException('TCPDF_FONTS no está disponible.');
		}

		$families = [];

		foreach (glob($this->sourceFonts . '/*.ttf') as $fontFile)
		{
			$baseName = pathinfo($fontFile, PATHINFO_FILENAME);

			// Regular, bold, italic, bolditalic según nombre
			$style = $this->detectFontStyleFromName($baseName);

			// Familia sin sufijos (regular, bold, etc.)
			$familyKey = $this->normalizeFontFamilyName($baseName, $style);

			// Nombre base para localizar archivos generados
			$baseKey = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $baseName));

			if ($force)
			{
				// Borramos cualquier conversión previa de esta fuente
				$patterns = [
					$this->tcpdfFonts . $baseKey . '*.php',
					$this->tcpdfFonts . $baseKey . '*.z',
					$this->tcpdfFonts . $baseKey . '*.ctg.z',
				];

				foreach ($patterns as $pattern)
				{
					foreach (glob($pattern) as $existingFile)
					{
						@unlink($existingFile);
					}
				}
			}

			// Convierte o reutiliza la fuente
			$fontName = TCPDF_FONTS::addTTFfont(
					$fontFile,
					'TrueTypeUnicode',
					'',
					32,
					$this->tcpdfFonts
			);

			if ($fontName === false)
			{
				continue;
			}

			$families[$familyKey]['family']			  ??= $familyKey;
			$families[$familyKey]['variants'][$style] = $fontName;
		}

		$this->registeredFamilies = $families;

		return $families;
	}

	/**
	 * Devuelve mapa de familias registradas.
	 */
	public function getRegisteredFonts(): array
	{
		return $this->registeredFamilies;
	}

	/**
	 * Renderiza una vista Blade estándar.
	 */
	private function renderView(string $view, array $data = []): string
	{
		return view($view, $data)->render();
	}

	/**
	 * Renderiza un archivo Blade desde una ruta arbitraria.
	 */
	private function renderBladeFile(string $absolutePath, array $data = []): string
	{
		if (!File::exists($absolutePath))
		{
			throw new \RuntimeException("No se encontró la plantilla: {$absolutePath}");
		}

		$template = File::get($absolutePath);

		return Blade::render($template, $data);
	}

	/**
	 * Renderiza una plantilla Blade desde un string.
	 */
	public function renderBladeString(string $content, array $data = []): string
	{
		return Blade::render($content, $data);
	}

	/**
	 * Crea una instancia Html2Pdf con opciones.
	 */
	public function createHtml2Pdf(array $options = []): Html2Pdf
	{
		$orientation = $options['orientation'] ?? 'P';
		$format		 = $options['format'] ?? 'LETTER';
		$lang		 = $options['lang'] ?? 'es';
		$margin		 = $options['margin'] ?? [5, 5, 5, 5];

		$pdf = new Html2Pdf($orientation, $format, $lang, true, 'UTF-8', $margin);

		if (!empty($options['default_font']))
		{
			$pdf->setDefaultFont($options['default_font']);
		}

		if (!empty($options['basepath']))
		{
			$pdf->setBasePath($options['basepath']);
		}

		return $pdf;
	}

	/* =============================================================
	 *                              GENERACIÓN
	 * ============================================================= */

	/**
	 * Genera un PDF desde HTML.
	 *
	 * @param string      $html
	 * @param array       $options
	 * @param string|null $appendPdfPath  Ruta local explícita para anexar al final (opcional)
	 */
	private function makePdfFromHtml(string $html, array $options = [], ?string $appendPdfPath = null): string
	{
		$this->registerFonts(false);
		$pdf = $this->createHtml2Pdf($options);

		$curr = getcwd();

		try
		{
			chdir(resource_path('views/'));
			$pdf->writeHTML($html);

			$binary = $pdf->output('', 'S'); // Devuelve binario

			if ($appendPdfPath)
			{
				$binary = $this->appendLocalPdfToPdfBinary($binary, $appendPdfPath);
			}

			return $binary;
		}
		catch (\Throwable $ex)
		{
			throw new \RuntimeException('Error generando PDF: ' . $ex->getMessage(), 0, $ex);
		}
		finally
		{
			chdir($curr);
		}
	}

	/**
	 * Genera un PDF desde vista Blade.
	 */
	public function makePdfFromView(string $view, array $data = [], array $options = [], ?string $appendPdfPath = null): string
	{
		$html = $this->renderView($view, $data);
		return $this->makePdfFromHtml($html, $options, $appendPdfPath);
	}

	/**
	 * Genera un PDF desde archivo Blade arbitrario.
	 */
	public function makePdfFromFile(string $absolutePath, array $data = [], array $options = [], ?string $appendPdfPath = null): string
	{
		$html = $this->renderBladeFile($absolutePath, $data);
		return $this->makePdfFromHtml($html, $options, $appendPdfPath);
	}

	/**
	 * Genera un PDF desde un string Blade.
	 */
	public function makePdfFromTemplateString(string $template, array $data = [], array $options = [], ?string $appendPdfPath = null): string
	{
		$html = $this->renderBladeString($template, $data);
		return $this->makePdfFromHtml($html, $options, $appendPdfPath);
	}

	/* =============================================================
	 *                                MERGE
	 * ============================================================= */

	/**
	 * Anexa un PDF local (ruta explícita) al final de un PDF binario ya generado.
	 */
	public function appendLocalPdfToPdfBinary(string $generatedPdfBinary, string $appendPdfPath): string
	{
		$appendPdfPath = $this->ensureReadablePdfPath($appendPdfPath);

		$tmpGenerated = $this->writeTempPdf($generatedPdfBinary, 'html2pdf_');

		try
		{
			return $this->mergeLocalPdfsToString([$tmpGenerated, $appendPdfPath]);
		}
		finally
		{
			$this->safeUnlink($tmpGenerated);
		}
	}

	/**
	 * Método genérico para unir 2 PDFs locales (rutas explícitas).
	 */
	public function mergeTwoLocalPdfs(string $firstPdfPath, string $secondPdfPath): string
	{
		$firstPdfPath  = $this->ensureReadablePdfPath($firstPdfPath);
		$secondPdfPath = $this->ensureReadablePdfPath($secondPdfPath);

		return $this->mergeLocalPdfsToString([$firstPdfPath, $secondPdfPath]);
	}

	/**
	 * Merge de N PDFs locales (en orden) y retorna el PDF resultante como binario.
	 */
	protected function mergeLocalPdfsToString(array $pdfPaths): string
	{
		if (count($pdfPaths) < 2)
		{
			throw new \InvalidArgumentException('mergeLocalPdfsToString requiere al menos 2 PDFs.');
		}

		$pdfPaths = array_map(fn($p) => $this->ensureReadablePdfPath($p), $pdfPaths);

		$tmpOut = $this->tempPdfPath('merged_');

		try
		{
			try
			{
				return $this->mergeWithWrapperToBinary($pdfPaths, $tmpOut);
			}
			catch (\Throwable $ex)
			{
				// Caso típico: FPDI free no soporta xref/object streams de PDFs modernos.
				if (!$this->isFpdiCompressionError($ex))
				{
					throw $ex;
				}

				// Intento de normalización SOLO si existen binarios/herramientas y ejecución está permitida.
				$normalized = [];
				try
				{
					foreach ($pdfPaths as $p)
					{
						$norm = $this->normalizePdfForFpdiIfPossible($p);
						if (!$norm)
						{
							throw new \RuntimeException(
											"Error haciendo merge de PDFs: Este servidor no puede anexar ese PDF (PDF moderno con compresión/streams no soportados por FPDI free) " .
											"y no hay herramientas disponibles/permisos para normalizarlo. " .
											"Soluciones: (1) convierte el PDF antes de subirlo (por ejemplo “Imprimir a PDF” o exportar a PDF 1.4), o (2) usa el parser comercial de Setasign (FPDI PDF-Parser).",
											0,
											$ex
									);
						}
						$normalized[] = $norm;
					}

					return $this->mergeWithWrapperToBinary($normalized, $tmpOut);
				}
				finally
				{
					foreach ($normalized as $n)
					{
						$this->safeUnlink($n);
					}
				}
			}
		}
		catch (\Throwable $ex)
		{
			throw new \RuntimeException('Error haciendo merge de PDFs: ' . $ex->getMessage(), 0, $ex);
		}
		finally
		{
			$this->safeUnlink($tmpOut);
		}
	}

	/**
	 * Merge usando oriceon/laravel-pdf-merger y retorna binario.
	 */
	private function mergeWithWrapperToBinary(array $pdfPaths, string $tmpOut): string
	{
		// Recomendado: usar el modo array del facade para evitar estado.
		$items = [];
		foreach ($pdfPaths as $p)
		{
			$items[] = [
				'filePath' => $p,
				'pages'	   => 'all',
			];
		}

		$result = PdfMerger::addPDF($items)
				->merge()
				->save($tmpOut, 'file'); // en el README se usa 'browser'; este driver soporta modos tipo file/string/browser
		// Fallback: si por alguna razón devolvió string (y no escribió a disco)
		if (is_string($result) && $result !== '' && !File::exists($tmpOut))
		{
			return $result;
		}

		if (!File::exists($tmpOut))
		{
			throw new \RuntimeException("PdfMerger no generó el archivo resultante: {$tmpOut}");
		}

		$content = File::get($tmpOut);
		if ($content === '')
		{
			throw new \RuntimeException("El PDF merged resultó vacío: {$tmpOut}");
		}

		return $content;
	}

	private function ensureReadablePdfPath(string $path): string
	{
		$path = trim($path);

		if ($path === '')
		{
			throw new \InvalidArgumentException('La ruta del PDF está vacía.');
		}

		if (!File::exists($path) || !is_file($path))
		{
			throw new \RuntimeException("No se encontró el PDF: {$path}");
		}

		if (!is_readable($path))
		{
			throw new \RuntimeException("El PDF no es legible: {$path}");
		}

		return $path;
	}

	private function isFpdiCompressionError(\Throwable $ex): bool
	{
		$msg = $ex->getMessage();
		return str_contains($msg, 'compression technique') && str_contains($msg, 'FPDI');
	}

	/* =============================================================
	 *                         NORMALIZACIÓN (OPCIONAL)
	 * ============================================================= */

	/**
	 * Intenta normalizar un PDF “moderno” a algo más compatible.
	 * Retorna path del PDF normalizado o null si no se puede (sin permisos/binarios).
	 */
	private function normalizePdfForFpdiIfPossible(string $inputPdfPath): ?string
	{
		// Si no podemos ejecutar procesos, no hay nada que hacer aquí.
		if (!$this->canExecuteExternalCommands())
		{
			return null;
		}

		// 1) qpdf
		if ($qpdf = $this->findBinaryInCommonPaths('qpdf'))
		{
			$out = $this->tempPdfPath('norm_qpdf_');
			$ok	 = $this->runExternalCommand([$qpdf, '--object-streams=disable', $inputPdfPath, $out]);

			if ($ok && File::exists($out) && filesize($out) > 0)
			{
				return $out;
			}
			$this->safeUnlink($out);
		}

		// 2) ghostscript (gs) => reescritura a PDF 1.4
		if ($gs = $this->findBinaryInCommonPaths('gs'))
		{
			$out = $this->tempPdfPath('norm_gs_');
			$ok	 = $this->runExternalCommand([
				$gs,
				'-sDEVICE=pdfwrite',
				'-dCompatibilityLevel=1.4',
				'-dNOPAUSE',
				'-dBATCH',
				'-sOutputFile=' . $out,
				$inputPdfPath,
			]);

			if ($ok && File::exists($out) && filesize($out) > 0)
			{
				return $out;
			}
			$this->safeUnlink($out);
		}

		return null;
	}

	private function canExecuteExternalCommands(): bool
	{
		// proc_open (preferido)
		if (function_exists('proc_open') && !$this->isFunctionDisabled('proc_open'))
		{
			return true;
		}

		// exec
		if (function_exists('exec') && !$this->isFunctionDisabled('exec'))
		{
			return true;
		}

		// shell_exec
		if (function_exists('shell_exec') && !$this->isFunctionDisabled('shell_exec'))
		{
			return true;
		}

		return false;
	}

	private function isFunctionDisabled(string $name): bool
	{
		$disabled = (string) ini_get('disable_functions');
		if ($disabled === '')
		{
			return false;
		}

		$list = array_map('trim', explode(',', $disabled));
		return in_array($name, $list, true);
	}

	private function findBinaryInCommonPaths(string $name): ?string
	{
		$name = trim($name);
		if ($name === '')
		{
			return null;
		}

		$candidates = [
			"/usr/bin/{$name}",
			"/usr/local/bin/{$name}",
			"/bin/{$name}",
			"/sbin/{$name}",
			"/usr/sbin/{$name}",
		];

		foreach ($candidates as $p)
		{
			if (@is_file($p) && @is_executable($p))
			{
				return $p;
			}
		}

		return null;
	}

	/**
	 * Ejecuta comando externo (si está permitido).
	 * Devuelve true si el comando “parece” haber corrido OK.
	 */
	private function runExternalCommand(array $cmd): bool
	{
		// proc_open
		if (function_exists('proc_open') && !$this->isFunctionDisabled('proc_open'))
		{
			$descriptors = [
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			];

			$process = @proc_open($cmd, $descriptors, $pipes);
			if (!\is_resource($process))
			{
				return false;
			}

			try
			{
				fclose($pipes[0]);
				stream_get_contents($pipes[1]);
				stream_get_contents($pipes[2]);
				fclose($pipes[1]);
				fclose($pipes[2]);

				$exitCode = proc_close($process);
				return $exitCode === 0;
			}
			catch (\Throwable)
			{
				try
				{
					proc_terminate($process);
				}
				catch (\Throwable)
				{
					
				}
				return false;
			}
		}

		// exec
		if (function_exists('exec') && !$this->isFunctionDisabled('exec'))
		{
			$command = $this->buildShellCommand($cmd) . ' 2>&1';
			$out	 = [];
			$code	 = 1;
			@exec($command, $out, $code);
			return $code === 0;
		}

		// shell_exec (sin exit code confiable)
		if (function_exists('shell_exec') && !$this->isFunctionDisabled('shell_exec'))
		{
			$command = $this->buildShellCommand($cmd) . ' 2>&1';
			$res	 = @shell_exec($command);
			return $res !== null;
		}

		return false;
	}

	private function buildShellCommand(array $cmd): string
	{
		$parts = [];
		foreach ($cmd as $i => $p)
		{
			if ($i === 0)
			{
				$parts[] = escapeshellcmd((string) $p);
			}
			else
			{
				$parts[] = escapeshellarg((string) $p);
			}
		}
		return implode(' ', $parts);
	}

	/* =============================================================
	 *                           TEMP FILES
	 * ============================================================= */

	protected function tempPdfPath(string $prefix = 'pdf_'): string
	{
		$base = tempnam(sys_get_temp_dir(), $prefix);
		if ($base === false)
		{
			throw new \RuntimeException('No se pudo crear archivo temporal para PDF.');
		}

		// Asegurar extensión .pdf (algunas libs son sensibles)
		$pdfPath = $base . '.pdf';
		@rename($base, $pdfPath);

		return File::exists($pdfPath) ? $pdfPath : $base;
	}

	protected function writeTempPdf(string $binary, string $prefix = 'pdf_'): string
	{
		$path = $this->tempPdfPath($prefix);

		$bytes = @file_put_contents($path, $binary);
		if ($bytes === false)
		{
			$this->safeUnlink($path);
			throw new \RuntimeException("No se pudo escribir PDF temporal: {$path}");
		}

		return $path;
	}

	protected function safeUnlink(?string $path): void
	{
		if ($path && File::exists($path))
		{
			@unlink($path);
		}
	}

	/* =============================================================
	 *                                Helpers
	 * ============================================================= */

	protected function detectFontStyleFromName(string $name): string
	{
		$lower = strtolower($name);

		if (str_contains($lower, 'bolditalic') || str_contains($lower, 'italicbold'))
		{
			return 'bolditalic';
		}

		if (str_contains($lower, 'bold'))
		{
			return 'bold';
		}

		if (str_contains($lower, 'italic') || str_contains($lower, 'oblique'))
		{
			return 'italic';
		}

		return 'regular';
	}

	protected function normalizeFontFamilyName(string $name, string $style): string
	{
		$clean = $name;

		$replacements = [
			'bolditalic', 'italicbold',
			'bold', 'italic', 'oblique',
			'regular', 'normal',
			'thin', 'extralight', 'light',
			'medium', 'semibold', 'extrabold', 'black'
		];

		foreach ($replacements as $suffix)
		{
			$clean = preg_replace("/[-_ ]?" . $suffix . "$/i", '', $clean);
		}

		$clean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $clean));

		return $clean ?: 'unknownfont';
	}

	/* =============================================================
	 *                 VALIDACIÓN PDF (merge compatible)
	 * ============================================================= */

	public const PDF_INCOMPATIBLE_MERGE_MESSAGE = "PDF incompatible, expórtalo como PDF 1.4 o ‘Imprimir a PDF’";

	public function assertPdfMergeCompatibleUpload(UploadedFile $file): void
	{
		$this->assertPdfMergeCompatiblePath($file->getPathname());
	}

	public function assertPdfMergeCompatiblePath(string $path): void
	{
		$path = $this->ensureReadablePdfPath($path);

		$version = $this->readPdfHeaderVersion($path);

		// Heurística: PDFs con cabecera >= 1.5 suelen usar xref/object streams (rompe FPDI free)
		if ($version !== null && version_compare($version, '1.5', '>='))
		{
			throw new \RuntimeException(self::PDF_INCOMPATIBLE_MERGE_MESSAGE);
		}

		// Escaneo adicional: detecta estructuras modernas típicas
		if ($this->pdfLooksLikeUsesObjectStreams($path))
		{
			throw new \RuntimeException(self::PDF_INCOMPATIBLE_MERGE_MESSAGE);
		}
	}

	protected function readPdfHeaderVersion(string $path): ?string
	{
		$fh = @fopen($path, 'rb');
		if (!$fh)
		{
			return null;
		}

		try
		{
			$head = (string) fread($fh, 64);
		}
		finally
		{
			@fclose($fh);
		}

		if (preg_match('/%PDF-(\d+\.\d+)/', $head, $m))
		{
			return $m[1];
		}

		return null;
	}

	protected function pdfLooksLikeUsesObjectStreams(string $path): bool
	{
		$size = @filesize($path);
		if (!is_int($size) || $size <= 0)
		{
			return false;
		}

		$needles = [
			'/ObjStm',
			'/Type/XRef',
		];

		$max = 4 * 1024 * 1024;

		if ($size <= $max)
		{
			$content = @file_get_contents($path);
			if ($content === false)
				return false;

			foreach ($needles as $n)
			{
				if (stripos($content, $n) !== false)
					return true;
			}
			return false;
		}

		$chunk = 2 * 1024 * 1024;

		$head = @file_get_contents($path, false, null, 0, $chunk);
		if ($head !== false)
		{
			foreach ($needles as $n)
			{
				if (stripos($head, $n) !== false)
					return true;
			}
		}

		$tailOffset = max(0, $size - $chunk);
		$tail		= @file_get_contents($path, false, null, $tailOffset, $chunk);
		if ($tail !== false)
		{
			foreach ($needles as $n)
			{
				if (stripos($tail, $n) !== false)
					return true;
			}
		}

		return false;
	}
}
