<?php

return [
    'history_sync' => [
        'chunk_size' => env('HISTORY_SYNC_CHUNK_SIZE', 50),
        'chunk_delay_seconds' => env('HISTORY_SYNC_CHUNK_DELAY', 30),
        'max_users_per_request' => env('HISTORY_SYNC_MAX_USERS', 10000),
    ],
];
