<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master Data Sync Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the connection details, tokens, and settings for
    | integrating the transactional POS system with the master website.
    |
    */

    'sync_token' => env('MASTER_DATA_SYNC_TOKEN', 'piyoh_sync_secret_2026!'),
];
