<?php
/**
 * Test Script for Video Processing API
 * Use this to test the API locally
 */

require __DIR__ . '/config.php';
require __DIR__ . '/VideoProcessor.php';

echo "Video Processing API Test\n";
echo "=========================\n\n";

// Load configuration
$config = require __DIR__ . '/config.php';

// Test 1: Check FFmpeg
echo "Test 1: Checking FFmpeg...\n";
$ffmpegPath = $config['ffmpeg_binary'];
exec($ffmpegPath . ' -version 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "✓ FFmpeg is installed and accessible\n";
    echo "  Location: {$ffmpegPath}\n";
    echo "  Version: " . $output[0] . "\n\n";
} else {
    echo "✗ FFmpeg not found at {$ffmpegPath}\n";
    echo "  Please update config.php with correct FFmpeg path\n\n";
}

// Test 2: Check directories
echo "Test 2: Checking directories...\n";
$dirs = [
    'Videos' => $config['videos_dir'],
    'HLS' => $config['hls_dir'],
    'Logs' => dirname($config['log_file'])
];

foreach ($dirs as $name => $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir);
        echo ($writable ? '✓' : '✗') . " {$name} directory: {$dir}";
        echo ($writable ? " (writable)\n" : " (NOT writable)\n");
    } else {
        echo "✗ {$name} directory does not exist: {$dir}\n";
    }
}
echo "\n";

// Test 3: Check PHP extensions
echo "Test 3: Checking PHP extensions...\n";
$extensions = ['curl', 'json'];

foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo ($loaded ? '✓' : '✗') . " {$ext} extension";
    echo ($loaded ? " (loaded)\n" : " (NOT loaded)\n");
}
echo "\n";

// Test 4: Test video processing (optional)
echo "Test 4: Video Processing Test\n";
echo "To test video processing, provide a test video URL:\n";
echo "Example: php test.php https://example.com/test-video.mp4\n\n";

if (isset($argv[1])) {
    $testUrl = $argv[1];
    echo "Processing video: {$testUrl}\n";
    
    $processor = new VideoProcessor($config);
    $result = $processor->processVideo($testUrl, 9999);
    
    echo "\nResult:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Skipping video processing test (no URL provided)\n";
}

echo "\n=========================\n";
echo "Test completed.\n";
