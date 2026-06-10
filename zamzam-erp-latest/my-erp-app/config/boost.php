<?php

declare(strict_types=1);

return [
    'enabled' => env('BOOST_ENABLED', true),

    'browser_logs_watcher' => env('BOOST_BROWSER_LOGS_WATCHER', true),

    'executable_paths' => [
        'php' => env('BOOST_PHP_EXECUTABLE_PATH'),
        'composer' => env('BOOST_COMPOSER_EXECUTABLE_PATH'),
        'npm' => env('BOOST_NPM_EXECUTABLE_PATH'),
        'vendor_bin' => env('BOOST_VENDOR_BIN_EXECUTABLE_PATH'),
        'current_directory' => env('BOOST_CURRENT_DIRECTORY_EXECUTABLE_PATH'),
    ],

    'enforce_tests' => env('BOOST_ENFORCE_TESTS', false),
];
