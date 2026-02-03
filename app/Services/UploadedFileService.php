<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class UploadedFileService
{

	/**
	 * Almacena un archivo subido y crea el registro en la tabla files.
	 *
	 * @param  UploadedFile  $uploadedFile
	 * @param  array|null    $meta             Metadatos opcionales (se guardan en files.meta)
	 * @param  int|null      $uploadedByUserId ID de usuario que sube el archivo (nullable)
	 * @param  string|null   $disk             Disk de Laravel; si null usa uploads.default_disk o filesystems.default
	 * @param  string|null   $basePath         Carpeta base dentro del disk (ej: 'plans/terms'); si null, 'uploads'
	 * @return File
	 */
	public function store(
			UploadedFile $uploadedFile,
			?array $meta = null,
			?int $uploadedByUserId = null,
			?string $disk = null,
			?string $basePath = null
	): File
	{
		// Disk por defecto
		$disk = $disk ?: config('uploads.default_disk', config('filesystems.default', 'local'));

		// Carpeta base dentro del disk
		$basePath = trim($basePath ?: 'uploads', '/');

		// Guardar físicamente el archivo
		$storedPath = $uploadedFile->store($basePath, [
			'disk' => $disk,
		]);

		if (!$storedPath)
		{
			throw new RuntimeException("No se pudo almacenar el archivo en el disk [$disk].");
		}

		// Crear registro en files
		$file				 = new File();
		$file->uuid			 = (string) Str::uuid();
		$file->disk			 = $disk;
		$file->path			 = $storedPath;
		$file->original_name = $uploadedFile->getClientOriginalName();
		$file->mime_type	 = $uploadedFile->getMimeType();
		$file->size			 = $uploadedFile->getSize();
		$file->uploaded_by	 = $uploadedByUserId;
		$file->meta			 = $meta;

		$file->save();

		// Si la caché está habilitada, precalentar cache local
		if ($this->isCacheEnabled())
		{
			$this->getLocalPath($file, true);
		}

		return $file;
	}

	/**
	 * Reemplaza el contenido físico de un archivo manteniendo el mismo registro File.
	 *
	 * @param  File          $file
	 * @param  UploadedFile  $uploadedFile
	 * @param  array|null    $meta                 Si se pasa, sustituye files.meta; si null, se conserva la anterior
	 * @param  bool          $deleteOldPhysical    Si true, se intenta borrar el archivo físico anterior
	 * @param  string|null   $disk                 Nuevo disk; si null se usa el mismo que ya tiene el File
	 * @param  string|null   $basePath             Carpeta base dentro del disk
	 * @return File
	 */
	public function replace(
			File $file,
			UploadedFile $uploadedFile,
			?array $meta = null,
			?int $uploadedByUserId = null,
			bool $deleteOldPhysical = true,
			?string $disk = null,
			?string $basePath = null
	): File
	{
		$oldDisk = $file->disk;
		$oldPath = $file->path;

		// Nuevo disk o el default
		$disk = $disk ?: config('uploads.default_disk', config('filesystems.default', 'local'));

		$basePath = trim($basePath ?: 'uploads', '/');

		// Guardar nuevo archivo físico
		$storedPath = $uploadedFile->store($basePath, [
			'disk' => $disk,
		]);

		if (!$storedPath)
		{
			throw new RuntimeException("No se pudo reemplazar el archivo en el disk [$disk].");
		}

		// Borrar archivo físico antiguo si se indica
		if ($deleteOldPhysical && $oldDisk && $oldPath)
		{
			try
			{
				Storage::disk($oldDisk)->delete($oldPath);
			}
			catch (\Throwable $e)
			{
				// No interrumpir el flujo si falla el borrado físico
			}
		}

		// Actualizar registro File (mismo id / uuid)
		$file->disk			 = $disk;
		$file->path			 = $storedPath;
		$file->original_name = $uploadedFile->getClientOriginalName();
		$file->mime_type	 = $uploadedFile->getMimeType();
		$file->uploaded_by	 = $uploadedByUserId;
		$file->size			 = $uploadedFile->getSize();

		if ($meta !== null)
		{
			$file->meta = $meta;
		}

		$file->save();

		// Refrescar caché si está habilitada
		if ($this->isCacheEnabled())
		{
			$this->getLocalPath($file, true);
		}

		return $file;
	}

	/**
	 * Elimina el archivo (registro y, opcionalmente, contenido físico y caché).
	 *
	 * @param  File  $file
	 * @param  bool  $deletePhysical  Si true, elimina el archivo físico principal
	 * @param  bool  $deleteCache     Si true, intenta eliminar también la copia en caché (si existe)
	 * @return bool                   true si el registro fue eliminado
	 */
	public function delete(
			File $file,
			bool $deletePhysical = true,
			bool $deleteCache = true
	): bool
	{
		$disk = $file->disk;
		$path = $file->path;

		// Eliminar archivo físico principal
		if ($deletePhysical && $disk && $path)
		{
			try
			{
				Storage::disk($disk)->delete($path);
			}
			catch (\Throwable $e)
			{
				// Ignorar errores de borrado físico
			}
		}

		// Eliminar caché asociada (si la hubiera)
		if ($deleteCache)
		{
			$this->deleteCacheForFile($file);
		}

		return (bool) $file->delete();
	}

	/**
	 * Devuelve una ruta absoluta local al archivo.
	 *
	 * - Si el disk es local → devuelve la ruta directa.
	 * - Si el disk es remoto:
	 *   * Si la caché está habilitada:
	 *       - Reutiliza copia en caché si existe (y no hay forceRefresh).
	 *       - Si no existe o forceRefresh, la descarga, la guarda en caché y devuelve esa ruta.
	 *   * Si la caché está deshabilitada:
	 *       - NO actualiza la caché persistente existente.
	 *       - Genera SIEMPRE una copia local temporal con ruta única, sin sobreescribir el caché previo.
	 *
	 * @param  File  $file
	 * @param  bool  $forceRefresh  Solo aplica cuando la caché está habilitada.
	 * @return string               Ruta absoluta local al archivo
	 */
	public function getLocalPath(File $file, bool $forceRefresh = false): string
	{
		$sourceDisk = $file->disk ?: config('uploads.default_disk', config('filesystems.default', 'local'));

		$driver = config("filesystems.disks.{$sourceDisk}.driver", 'local');

		// Caso 1: disk local → devolvemos path directo
		if ($driver === 'local')
		{
			return Storage::disk($sourceDisk)->path($file->path);
		}

		// A partir de aquí, el disk es remoto (s3, etc.)
		$cacheConfig   = config('uploads.cache', []);
		$cacheEnabled  = (bool) ($cacheConfig['enabled'] ?? true);
		$cacheDiskName = $cacheConfig['disk'] ?? 'local';
		$cacheRoot	   = trim($cacheConfig['root'] ?? 'file_cache', '/');
		$basename	   = basename($file->path) ?: 'file';
		$prefix		   = $file->uuid ?: (string) ($file->id ?? Str::random(8));

		// Si la caché está habilitada → usamos caché persistente
		if ($cacheEnabled)
		{
			$cacheRelativePath = $cacheRoot . '/' . $prefix . '_' . $basename;

			// Reutilizar si ya existe y no hay forceRefresh
			if (!$forceRefresh && Storage::disk($cacheDiskName)->exists($cacheRelativePath))
			{
				return Storage::disk($cacheDiskName)->path($cacheRelativePath);
			}

			// Descargar del disk remoto y guardar en caché
			$stream = Storage::disk($sourceDisk)->readStream($file->path);
			if ($stream === false)
			{
				throw new RuntimeException("No se pudo leer el archivo [{$file->id}] desde el disk [$sourceDisk].");
			}

			Storage::disk($cacheDiskName)->put($cacheRelativePath, $stream);
			if (is_resource($stream))
			{
				@fclose($stream);
			}

			return Storage::disk($cacheDiskName)->path($cacheRelativePath);
		}

		// Si la caché está deshabilitada:
		// - NO tocamos ni reutilizamos el caché persistente existente.
		// - Generamos siempre una copia local temporal con ruta única.
		$tempDiskName = $cacheDiskName ?: 'local';
		$tempRoot	  = $cacheRoot ?: 'file_cache';

		// Ruta única para no sobreescribir ningún archivo previo
		$tempRelativePath = rtrim($tempRoot, '/') . '/tmp_' . $prefix . '_' . Str::uuid() . '_' . $basename;

		$stream = Storage::disk($sourceDisk)->readStream($file->path);
		if ($stream === false)
		{
			throw new RuntimeException("No se pudo leer el archivo [{$file->id}] desde el disk [$sourceDisk].");
		}

		Storage::disk($tempDiskName)->put($tempRelativePath, $stream);
		if (is_resource($stream))
		{
			@fclose($stream);
		}

		return Storage::disk($tempDiskName)->path($tempRelativePath);
	}

	// ---------------------------------------------------------------------
	// Helpers privados
	// ---------------------------------------------------------------------

	/**
	 * Indica si la caché de archivos está habilitada según config/uploads.php.
	 *
	 * @return bool
	 */
	protected function isCacheEnabled(): bool
	{
		$cacheConfig = config('uploads.cache', []);
		return (bool) ($cacheConfig['enabled'] ?? true);
	}

	/**
	 * Intenta eliminar el archivo en caché asociado a un File, si existe.
	 *
	 * Nota: esto solo intenta limpiar la caché "persistente" basada en uuid/id.
	 *
	 * @param  File  $file
	 * @return void
	 */
	protected function deleteCacheForFile(File $file): void
	{
		$cacheConfig   = config('uploads.cache', []);
		$cacheDiskName = $cacheConfig['disk'] ?? 'local';
		$cacheRoot	   = trim($cacheConfig['root'] ?? 'file_cache', '/');

		$basename		   = basename($file->path) ?: 'file';
		$prefix			   = $file->uuid ?: (string) ($file->id ?? Str::random(8));
		$cacheRelativePath = $cacheRoot . '/' . $prefix . '_' . $basename;

		try
		{
			if (Storage::disk($cacheDiskName)->exists($cacheRelativePath))
			{
				Storage::disk($cacheDiskName)->delete($cacheRelativePath);
			}
		}
		catch (\Throwable $e)
		{
			// No interrumpir si falla el borrado de caché
		}
	}

	/**
	 * Duplica un registro File y su archivo físico en disco.
	 *
	 * IMPORTANTE: ajusta los nombres de columnas (disk, path, uuid, meta, uploaded_by)
	 * si en tu modelo File se llaman distinto.
	 */
	public function duplicateFile(
			File $sourceFile,
			?array $meta = null,
			?int $uploadedByUserId = null,
			?string $disk = null,
			?string $basePath = null
	): File
	{
		$disk		= $disk ?? ($sourceFile->disk ?? config('filesystems.default'));
		$sourcePath = $sourceFile->path; // ajusta si tu columna se llama distinto

		if (!$sourcePath || !Storage::disk($disk)->exists($sourcePath))
		{
			throw new \RuntimeException('No se encontró el archivo origen para duplicar.');
		}

		// Directorio destino
		$basePath	= $basePath ?? dirname($sourcePath);
		$ext		= pathinfo($sourcePath, PATHINFO_EXTENSION);
		$uuid		= (string) Str::uuid();
		$fileName	= $uuid . ($ext ? ('.' . $ext) : '');
		$targetPath = trim($basePath, '/') . '/' . $fileName;

		// Copiamos archivo físico
		Storage::disk($disk)->copy($sourcePath, $targetPath);

		// Creamos nuevo registro en files a partir del original
		$clone = $sourceFile->replicate([
			'id', // nunca copiamos ID
			// si en tu tabla no existen estas columnas, simplemente quítalas de aquí
			'uuid',
			'path',
			'meta',
			'uploaded_by',
		]);

		// Rellenamos campos que cambian
		if ($clone->isFillable('uuid'))
		{
			$clone->uuid = $uuid;
		}

		$clone->path		= $targetPath;
		$clone->meta		= $meta ?? $sourceFile->meta;
		$clone->uploaded_by = $uploadedByUserId ?? $sourceFile->uploaded_by;
		$clone->created_at	= now();
		$clone->updated_at	= now();
		$clone->save();

		return $clone;
	}
}
