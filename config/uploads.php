<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco por defecto para archivos subidos
    |--------------------------------------------------------------------------
    |
    | Nombre del disk de Laravel (config/filesystems.php) que se usará por
    | defecto para almacenar archivos de la aplicación.
    |
    */

    'default_disk' => env('UPLOADS_DEFAULT_DISK', 'local'),
	'temporary_url_minutes' => 1,

    /*
    |--------------------------------------------------------------------------
    | Configuración de caché local de archivos
    |--------------------------------------------------------------------------
    |
    | Se usa para getLocalPath(): cuando el archivo está en un disk remoto
    | (por ejemplo S3), se descarga a este disk/carpeta local para poder
    | obtener una ruta absoluta usable en generación de PDFs, etc.
    |
    */

    'cache' => [
        // Nuevo flag
        'enabled' => env('UPLOADS_CACHE_ENABLED', true),

        // Disk local donde se guarda la copia en caché
        'disk' => env('UPLOADS_CACHE_DISK', 'local'),

        // Carpeta dentro de ese disk
        'root' => env('UPLOADS_CACHE_ROOT', 'file_cache'),
    ],
];
