<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Release Type
    |--------------------------------------------------------------------------
    |
    | Supported values: major, minor, patch, critical_fix, security, hotfix,
    | maintenance, initial.
    |
    */
    'type' => env('APP_RELEASE_TYPE', 'major'),

    'date' => env('APP_RELEASE_DATE', '2026-06-21'),

    'commit' => env('SOURCE_COMMIT')
        ?: env('COOLIFY_GIT_COMMIT_SHA')
        ?: env('GIT_COMMIT')
        ?: null,
];
