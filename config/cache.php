<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => 'cache',
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => 'cache_locks',
        ],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'incidencias')).'-cache-'),
    'serializable_classes' => false,
];
