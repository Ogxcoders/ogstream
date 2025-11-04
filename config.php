<?php
/**
 * Configuration for Web B Video Processing API
 */

return [
    // API Settings
    // SECURITY: REQUIRED - Set a strong random API key before deployment
    // Generate with: openssl rand -hex 32
    'api_key' => 'ogs32', // REQUIRED for security
    'allowed_origins' => [], // SECURITY: Add specific domains like ['https://web-a.example.com']
    
    // Directory Settings
    'videos_dir' => __DIR__ . '/videos', // Directory for original downloaded videos
    'hls_dir' => __DIR__ . '/hls', // Directory for HLS output files
    
    // Public URL Settings
    'base_url' => 'https://v.trendss.net/', // Your Web B domain
    'hls_url_base' => 'https://v.trendss.net/hls', // Public URL to HLS directory
    
    // FFmpeg Settings
    'ffmpeg_binary' => '/usr/bin/ffmpeg', // Path to FFmpeg binary
    'ffmpeg_timeout' => 600, // Timeout in seconds (10 minutes)
    
    // Video Processing Settings
    'resolutions' => [
        '240p' => [
            'scale' => '240:-2',
            'bitrate' => '200k',
            'maxrate' => '250k',
            'bufsize' => '400k'
        ],
        '360p' => [
            'scale' => '360:-2',
            'bitrate' => '350k',
            'maxrate' => '400k',
            'bufsize' => '700k'
        ],
        '480p' => [
            'scale' => '480:-2',
            'bitrate' => '500k',
            'maxrate' => '600k',
            'bufsize' => '1000k'
        ]
    ],
    
    // HLS Settings
    'hls_time' => 6, // Segment duration in seconds
    'hls_list_size' => 0, // 0 = keep all segments in playlist
    
    // Cleanup Settings
    'cleanup_original' => false, // Set to true to delete original videos after processing
    'max_video_age_days' => 30, // Delete videos older than this (0 = never delete)
    
    // Logging
    'log_file' => __DIR__ . '/logs/api.log',
    'debug' => true
];
