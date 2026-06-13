<?php

return [
    'retention' => env('BACKUP_RETAIN_FILES', 10),

    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),

    'app' => [
        'database_connection' => env('BACKUP_DB_CONNECTION'),
        'directory' => 'backups/app',
        'include_paths' => [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'storage/app/public',
            '.env',
            'artisan',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'vite.config.js',
        ],
        'exclude_paths' => [
            '.git',
            'node_modules',
            'vendor',
            'storage/app/private/backups',
            'storage/framework',
            'storage/logs',
            'database/database.sqlite',
            'database/demo.sqlite',
        ],
    ],

    'google_drive' => [
        'enabled' => env('GOOGLE_DRIVE_BACKUP_ENABLED', false),
        'auto_upload' => env('GOOGLE_DRIVE_BACKUP_AUTO_UPLOAD', false),
        'folder_id' => env('GOOGLE_DRIVE_BACKUP_FOLDER_ID'),
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'),
        'service_account_path' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH'),
    ],
];
