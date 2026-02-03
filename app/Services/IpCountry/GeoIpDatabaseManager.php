<?php

namespace App\Services\IpCountry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GeoIp2\Database\Reader;

class GeoIpDatabaseManager
{
    private ?Reader $reader = null;

    public function getReader(): Reader
    {
        $this->ensureFreshDatabase(false);

        if ($this->reader === null) {
            $this->reader = new Reader($this->mmdbPath());
        }

        return $this->reader;
    }

    public function ensureFreshDatabase(bool $force = false): void
    {
        $provider = config('ip_country.default');
        $cfg = config("ip_country.providers.$provider");

        if (!$cfg) {
            throw new \RuntimeException("IP Country provider [$provider] no está configurado.");
        }

        $dir = rtrim(config('ip_country.storage_dir'), DIRECTORY_SEPARATOR);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear storage dir: $dir");
        }

        $mmdb = $this->mmdbPath();
        $lockFile = $mmdb . '.lock';

        // Fast path: existe y no está vencida
        if (!$force && is_file($mmdb) && !$this->isExpired($mmdb, (int)($cfg['ttl_days'] ?? 30))) {
            return;
        }

        $fp = @fopen($lockFile, 'c+');
        if (!$fp) {
            throw new \RuntimeException("No se pudo abrir lock file: $lockFile");
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException("No se pudo adquirir lock: $lockFile");
            }

            // Re-check dentro del lock (por si otro worker ya actualizó)
            if (!$force && is_file($mmdb) && !$this->isExpired($mmdb, (int)($cfg['ttl_days'] ?? 30))) {
                return;
            }

            $this->downloadAndInstall($provider, $cfg, $mmdb);

            // Reset reader (para que reabra el archivo nuevo)
            $this->reader = null;

        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    private function mmdbPath(): string
    {
        $dir = rtrim(config('ip_country.storage_dir'), DIRECTORY_SEPARATOR);
        return $dir . DIRECTORY_SEPARATOR . 'ip-country.mmdb';
    }

    private function isExpired(string $path, int $ttlDays): bool
    {
        $mtime = @filemtime($path);
        if (!$mtime) return true;

        $ageSeconds = time() - $mtime;
        return $ageSeconds > ($ttlDays * 86400);
    }

    private function downloadAndInstall(string $provider, array $cfg, string $targetMmdb): void
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ip_country_' . Str::random(12);
        if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException("No se pudo crear tmp dir: $tmpDir");
        }

        try {
            $type = $cfg['type'] ?? null;

            switch ($type) {
                case 'mmdb':
                    $url = $cfg['download_url'] ?? null;
                    if (!$url) throw new \RuntimeException("Falta download_url para [$provider].");

                    $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'db.mmdb';
                    $this->httpDownloadTo($url, $tmpFile, (int)($cfg['http_timeout_seconds'] ?? 60));
                    $this->atomicReplace($tmpFile, $targetMmdb);
                    break;

                case 'mmdb_gz_discover':
                    $pageUrl = $cfg['discover_page_url'] ?? null;
                    $regex = $cfg['discover_regex'] ?? null;
                    if (!$pageUrl || !$regex) {
                        throw new \RuntimeException("Falta discover_page_url/discover_regex para [$provider].");
                    }

                    $timeout = (int)($cfg['http_timeout_seconds'] ?? 60);

                    $html = Http::timeout($timeout)->get($pageUrl)->body();
                    if (!preg_match($regex, $html, $m)) {
                        throw new \RuntimeException("No se pudo descubrir URL MMDB.GZ desde $pageUrl");
                    }

                    $gzUrl = $m[0];
                    $tmpGz = $tmpDir . DIRECTORY_SEPARATOR . 'db.mmdb.gz';
                    $tmpMmdb = $tmpDir . DIRECTORY_SEPARATOR . 'db.mmdb';

                    $this->httpDownloadTo($gzUrl, $tmpGz, $timeout);
                    $this->gunzipTo($tmpGz, $tmpMmdb);
                    $this->atomicReplace($tmpMmdb, $targetMmdb);
                    break;

                case 'zip_mmdb':
                    if (empty($cfg['token'])) {
                        throw new \RuntimeException("IP2Location: falta IP2LOCATION_DOWNLOAD_TOKEN.");
                    }
                    $template = $cfg['download_url_template'] ?? null;
                    $fileCode = $cfg['file_code'] ?? null;
                    if (!$template || !$fileCode) {
                        throw new \RuntimeException("IP2Location: falta download_url_template o file_code.");
                    }

                    $url = str_replace(
                        ['{token}', '{file}'],
                        [rawurlencode($cfg['token']), rawurlencode($fileCode)],
                        $template
                    );

                    $timeout = (int)($cfg['http_timeout_seconds'] ?? 120);

                    $tmpZip = $tmpDir . DIRECTORY_SEPARATOR . 'db.zip';
                    $this->httpDownloadTo($url, $tmpZip, $timeout);

                    $tmpMmdb = $tmpDir . DIRECTORY_SEPARATOR . 'db.mmdb';
                    $this->extractFirstMmdbFromZip($tmpZip, $tmpMmdb);

                    $this->atomicReplace($tmpMmdb, $targetMmdb);
                    break;

                case 'archive_mmdb':
                    $url = $cfg['download_url'] ?? null;
                    if (!$url) throw new \RuntimeException("MaxMind: falta MAXMIND_DOWNLOAD_URL.");

                    $timeout = (int)($cfg['http_timeout_seconds'] ?? 120);
                    $archiveFormat = $cfg['archive_format'] ?? 'zip';

                    $tmpArchive = $tmpDir . DIRECTORY_SEPARATOR . 'db.' . ($archiveFormat === 'tar.gz' ? 'tar.gz' : 'zip');
                    $this->httpDownloadTo($url, $tmpArchive, $timeout, $cfg);

                    $tmpMmdb = $tmpDir . DIRECTORY_SEPARATOR . 'db.mmdb';

                    if ($archiveFormat === 'tar.gz') {
                        $this->extractFirstMmdbFromTarGz($tmpArchive, $tmpMmdb, $tmpDir);
                    } else {
                        $this->extractFirstMmdbFromZip($tmpArchive, $tmpMmdb);
                    }

                    $this->atomicReplace($tmpMmdb, $targetMmdb);
                    break;

                default:
                    throw new \RuntimeException("Tipo de proveedor no soportado: [$type] para [$provider].");
            }

            // Metadatos (útil para diagnóstico)
            $meta = [
                'provider' => $provider,
                'updated_at' => date('c'),
                'type' => $type,
            ];
            @file_put_contents(dirname($targetMmdb) . DIRECTORY_SEPARATOR . 'ip-country.meta.json', json_encode($meta, JSON_PRETTY_PRINT));

        } catch (\Throwable $e) {
            Log::error('[GeoIpDatabaseManager] Error actualizando DB', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $this->rrmdir($tmpDir);
        }
    }

    private function httpDownloadTo(string $url, string $dest, int $timeoutSeconds, array $cfg = []): void
    {
        $req = Http::timeout($timeoutSeconds)
            ->withOptions(['allow_redirects' => true])
            ->sink($dest);

        // Para MaxMind (si usas basic auth). 
        if (!empty($cfg['account_id']) && !empty($cfg['license_key'])) {
            $req = $req->withBasicAuth($cfg['account_id'], $cfg['license_key']);
        }

        $res = $req->get($url);

        if (!$res->successful()) {
            throw new \RuntimeException("Descarga fallida ($url): HTTP " . $res->status());
        }

        if (!is_file($dest) || filesize($dest) < 1024) {
            throw new \RuntimeException("Archivo descargado inválido o vacío: $dest");
        }
    }

    private function gunzipTo(string $srcGz, string $dest): void
    {
        $in = @gzopen($srcGz, 'rb');
        if (!$in) throw new \RuntimeException("No se pudo abrir gz: $srcGz");

        $out = @fopen($dest, 'wb');
        if (!$out) {
            @gzclose($in);
            throw new \RuntimeException("No se pudo abrir destino: $dest");
        }

        try {
            while (!gzeof($in)) {
                $buf = gzread($in, 1024 * 1024);
                if ($buf === false) break;
                fwrite($out, $buf);
            }
        } finally {
            @gzclose($in);
            @fclose($out);
        }

        if (!is_file($dest) || filesize($dest) < 1024) {
            throw new \RuntimeException("gunzip produjo archivo inválido: $dest");
        }
    }

    private function extractFirstMmdbFromZip(string $zipPath, string $destMmdb): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("ZipArchive no está disponible (instala/extiende php-zip).");
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("No se pudo abrir ZIP: $zipPath");
        }

        try {
            $mmdbIndex = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!$name) continue;

                if (preg_match('/\.mmdb$/i', $name) || preg_match('/\.MMDB$/', $name)) {
                    $mmdbIndex = $i;
                    break;
                }
            }

            if ($mmdbIndex === null) {
                throw new \RuntimeException("No se encontró .mmdb dentro del ZIP.");
            }

            $stream = $zip->getStream($zip->getNameIndex($mmdbIndex));
            if (!$stream) {
                throw new \RuntimeException("No se pudo abrir stream del .mmdb en ZIP.");
            }

            $out = fopen($destMmdb, 'wb');
            if (!$out) {
                fclose($stream);
                throw new \RuntimeException("No se pudo crear destino: $destMmdb");
            }

            try {
                while (!feof($stream)) {
                    $buf = fread($stream, 1024 * 1024);
                    if ($buf === false) break;
                    fwrite($out, $buf);
                }
            } finally {
                fclose($stream);
                fclose($out);
            }

        } finally {
            $zip->close();
        }

        if (!is_file($destMmdb) || filesize($destMmdb) < 1024) {
            throw new \RuntimeException("Extracción ZIP produjo archivo inválido: $destMmdb");
        }
    }

    private function extractFirstMmdbFromTarGz(string $tarGzPath, string $destMmdb, string $tmpDir): void
    {
        // Descomprime a .tar
        $tarPath = $tmpDir . DIRECTORY_SEPARATOR . 'db.tar';
        $this->gunzipTo($tarGzPath, $tarPath);

        try {
            $phar = new \PharData($tarPath);

            $mmdbFile = null;
            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                /** @var \PharFileInfo $file */
                if (preg_match('/\.mmdb$/i', $file->getFilename())) {
                    $mmdbFile = $file->getPathName();
                    break;
                }
            }

            if (!$mmdbFile) {
                throw new \RuntimeException("No se encontró .mmdb dentro del TAR.");
            }

            // Extraer solo ese archivo
            $extractDir = $tmpDir . DIRECTORY_SEPARATOR . 'tar_extract';
            @mkdir($extractDir, 0700, true);
            $phar->extractTo($extractDir, [$mmdbFile], true);

            // El path en extractTo conserva estructura; buscamos el .mmdb extraído
            $found = null;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractDir));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('/\.mmdb$/i', $f->getFilename())) {
                    $found = $f->getPathname();
                    break;
                }
            }

            if (!$found) {
                throw new \RuntimeException("No se encontró .mmdb extraído desde TAR.");
            }

            $this->atomicReplace($found, $destMmdb);

        } finally {
            @unlink($tarPath);
        }
    }

    private function atomicReplace(string $src, string $dest): void
    {
        $destDir = dirname($dest);
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            throw new \RuntimeException("No se pudo crear destino dir: $destDir");
        }

        $tmpDest = $dest . '.tmp';
        if (is_file($tmpDest)) @unlink($tmpDest);

        if (!@copy($src, $tmpDest)) {
            throw new \RuntimeException("No se pudo copiar a tmpDest: $tmpDest");
        }

        @chmod($tmpDest, 0644);

        if (is_file($dest)) {
            @unlink($dest);
        }

        if (!@rename($tmpDest, $dest)) {
            throw new \RuntimeException("No se pudo mover tmpDest a destino final: $dest");
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($dir);
    }
}
