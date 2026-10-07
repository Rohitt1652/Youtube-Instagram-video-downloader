<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MediaGrab Configuration
    |--------------------------------------------------------------------------
    */

    'max_duration' => (int) env('MAX_MEDIA_DURATION', 1800), // In seconds (30 mins)
    'max_download_size_mb' => (int) env('MAX_DOWNLOAD_SIZE_MB', 500), // In megabytes
    'download_timeout' => (int) env('DOWNLOAD_TIMEOUT', 300), // In seconds (5 mins)
    'temp_file_ttl_hours' => (int) env('TEMP_FILE_TTL_HOURS', 2), // Temporary file lifetime in hours

    'rate_limits' => [
        'info_per_minute' => (int) env('RATE_LIMIT_INFO_PER_MINUTE', 30),
        'download_per_minute' => (int) env('RATE_LIMIT_DOWNLOAD_PER_MINUTE', 10),
    ],

    'binaries' => [
        'ytdlp' => env('YTDLP_BINARY', (PHP_OS_FAMILY === 'Windows' ? 'python -m yt_dlp' : 'yt-dlp')),
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    ],

    'temp_directory' => storage_path('app/media_temp'),

    'allowed_domains' => [
        'youtube' => [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'youtu.be',
        ],
        'instagram' => [
            'instagram.com',
            'www.instagram.com',
            'm.instagram.com',
        ],
    ],
];
