<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Final public media may use the database-managed R2 disk, but a browser
    | first posts every FileUpload to Livewire's temporary endpoint. Pin that
    | short-lived stage to a dedicated local disk so it never silently changes
    | into a direct browser-to-R2 upload when FILESYSTEM_DISK is changed.
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_UPLOAD_DISK', 'livewire-tmp'),
        'rules' => ['required', 'file', 'max:12288'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
