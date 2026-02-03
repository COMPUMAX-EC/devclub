<?php

return [

    'default' => env('IP_COUNTRY_PROVIDER', 'iplocate'),

    'cache_ttl_seconds' => (int) env('IP_COUNTRY_CACHE_TTL_SECONDS', 86400),

    'storage_dir' => storage_path('app/ip-country'),

    /*
    |--------------------------------------------------------------------------
    | Fallback ISO2 (desde .env)
    |--------------------------------------------------------------------------
    */
    'fallback_iso2' => env('IP_COUNTRY_FALLBACK_ISO2', null),

    /*
    |--------------------------------------------------------------------------
    | Resolución de IP (desde .env)
    |--------------------------------------------------------------------------
    */
    'ip' => [
        'source' => env('IP_COUNTRY_IP_SOURCE', 'request_ip'), // request_ip|remote_addr|header|cloudflare|auto

        'header_name' => env('IP_COUNTRY_IP_HEADER_NAME', 'X-Forwarded-For'),

        'header_precedence' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('IP_COUNTRY_IP_HEADER_PRECEDENCE', 'CF-Connecting-IP,X-Forwarded-For,X-Real-IP'))
        ))),

        'xff_position' => env('IP_COUNTRY_XFF_POSITION', 'first'), // first|last

        'trusted_proxies' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('IP_COUNTRY_TRUSTED_PROXIES', ''))
        ))),
    ],

    'providers' => [

        'iplocate' => [
            'type' => 'mmdb',
            'ttl_days' => (int) env('IP_COUNTRY_TTL_DAYS_IPLOCATE', 7),
            'download_url' => env(
                'IP_COUNTRY_IPLOCATE_URL',
                'https://cdn.jsdelivr.net/npm/@ip-location-db/iplocate-country-mmdb/iplocate-country.mmdb'
            ),
        ],

        'dbip_lite' => [
            'type' => 'mmdb_gz_discover',
            'ttl_days' => (int) env('IP_COUNTRY_TTL_DAYS_DBIP', 35),
            'discover_page_url' => env('IP_COUNTRY_DBIP_PAGE', 'https://db-ip.com/db/download/ip-to-country-lite'),
            'discover_regex' => '#https://download\.db-ip\.com/free/dbip-country-lite-\d{4}-\d{2}\.mmdb\.gz#i',
            'http_timeout_seconds' => (int) env('IP_COUNTRY_DBIP_TIMEOUT', 60),
        ],

        'ip2location_lite' => [
            'type' => 'zip_mmdb',
            'ttl_days' => (int) env('IP_COUNTRY_TTL_DAYS_IP2LOCATION', 35),
            'token' => env('IP2LOCATION_DOWNLOAD_TOKEN'),
            'file_code' => env('IP2LOCATION_FILE_CODE', 'DB1LITEMMDB'),
            'download_url_template' => 'https://www.ip2location.com/download?token={token}&file={file}',
            'http_timeout_seconds' => (int) env('IP_COUNTRY_IP2LOCATION_TIMEOUT', 120),
        ],

        'maxmind_geolite2' => [
            'type' => 'archive_mmdb',
            'ttl_days' => (int) env('IP_COUNTRY_TTL_DAYS_MAXMIND', 30),
            'download_url' => env('MAXMIND_DOWNLOAD_URL'),
            'account_id' => env('MAXMIND_ACCOUNT_ID'),
            'license_key' => env('MAXMIND_LICENSE_KEY'),
            'archive_format' => env('MAXMIND_ARCHIVE_FORMAT', 'zip'),
            'http_timeout_seconds' => (int) env('IP_COUNTRY_MAXMIND_TIMEOUT', 120),
        ],
    ],
];
