<?php
/**
 * Security-First Setup Script for Web B API
 * Run this before deploying to production
 */

echo "==============================================\n";
echo "Web B Video Processing API - Security Setup\n";
echo "==============================================\n\n";

// Check if config.php exists
if (!file_exists(__DIR__ . '/config.php')) {
    echo "❌ ERROR: config.php not found!\n";
    exit(1);
}

$config = require __DIR__ . '/config.php';

echo "Step 1: Checking API Key Security...\n";
echo "-------------------------------------------\n";

// Check API key
$apiKeyStatus = '❌ INSECURE';
$apiKeyIssues = [];

if (empty($config['api_key'])) {
    $apiKeyIssues[] = "API key is empty";
} elseif ($config['api_key'] === 'CHANGE_ME_TO_A_SECURE_RANDOM_KEY') {
    $apiKeyIssues[] = "Using default placeholder API key";
} elseif (strlen($config['api_key']) < 32) {
    $apiKeyIssues[] = "API key is too short (minimum 32 characters recommended)";
} else {
    $apiKeyStatus = '✓ SECURE';
}

echo "API Key Status: {$apiKeyStatus}\n";
if (!empty($apiKeyIssues)) {
    echo "Issues:\n";
    foreach ($apiKeyIssues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\nGenerate a secure API key with:\n";
    echo "  openssl rand -hex 32\n";
    echo "\nOr use this one:\n";
    echo "  " . bin2hex(random_bytes(32)) . "\n\n";
}

echo "\nStep 2: Checking CORS Configuration...\n";
echo "-------------------------------------------\n";

$corsStatus = '❌ INSECURE';
$corsIssues = [];

if (empty($config['allowed_origins'])) {
    $corsStatus = '✓ SECURE (No CORS allowed)';
    echo "CORS Status: {$corsStatus}\n";
    echo "Note: This means only same-origin requests are allowed.\n";
} elseif (in_array('*', $config['allowed_origins'])) {
    $corsIssues[] = "Wildcard (*) allows any origin to access the API";
    echo "CORS Status: {$corsStatus}\n";
    echo "Issues:\n";
    foreach ($corsIssues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\nRecommendation: Specify exact domains like:\n";
    echo "  'allowed_origins' => ['https://web-a.example.com']\n\n";
} else {
    $corsStatus = '✓ SECURE';
    echo "CORS Status: {$corsStatus}\n";
    echo "Allowed origins:\n";
    foreach ($config['allowed_origins'] as $origin) {
        echo "  - {$origin}\n";
    }
    echo "\n";
}

echo "\nStep 3: Checking Directory Permissions...\n";
echo "-------------------------------------------\n";

$dirs = [
    'Videos' => $config['videos_dir'],
    'HLS' => $config['hls_dir'],
    'Logs' => dirname($config['log_file'])
];

$dirStatus = true;
foreach ($dirs as $name => $dir) {
    if (!is_dir($dir)) {
        echo "❌ {$name} directory does not exist: {$dir}\n";
        echo "   Creating...\n";
        mkdir($dir, 0755, true);
        echo "   ✓ Created\n";
    } else {
        $writable = is_writable($dir);
        if ($writable) {
            echo "✓ {$name} directory: {$dir} (writable)\n";
        } else {
            echo "❌ {$name} directory: {$dir} (NOT writable)\n";
            $dirStatus = false;
        }
    }
}

if (!$dirStatus) {
    echo "\nFix permissions with:\n";
    echo "  chmod -R 775 videos hls logs\n";
    echo "  chown -R www-data:www-data videos hls logs\n\n";
}

echo "\nStep 4: Checking FFmpeg...\n";
echo "-------------------------------------------\n";

$ffmpegPath = $config['ffmpeg_binary'];
exec($ffmpegPath . ' -version 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "✓ FFmpeg is installed and accessible\n";
    echo "  Location: {$ffmpegPath}\n";
    echo "  Version: " . $output[0] . "\n";
} else {
    echo "❌ FFmpeg not found at {$ffmpegPath}\n";
    echo "\nInstall FFmpeg with:\n";
    echo "  sudo apt install -y ffmpeg\n";
    echo "\nThen update config.php with the correct path.\n";
}

echo "\nStep 5: Checking PHP Extensions...\n";
echo "-------------------------------------------\n";

$extensions = ['curl', 'json'];
$extStatus = true;

foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    if ($loaded) {
        echo "✓ {$ext} extension loaded\n";
    } else {
        echo "❌ {$ext} extension NOT loaded\n";
        $extStatus = false;
    }
}

if (!$extStatus) {
    echo "\nInstall missing extensions and restart PHP.\n";
}

echo "\n==============================================\n";
echo "Security Summary\n";
echo "==============================================\n\n";

$securityScore = 0;
$maxScore = 3;

if ($apiKeyStatus === '✓ SECURE') {
    echo "✓ API Key: Secure\n";
    $securityScore++;
} else {
    echo "❌ API Key: INSECURE - MUST FIX BEFORE DEPLOYMENT\n";
}

if ($corsStatus === '✓ SECURE' || $corsStatus === '✓ SECURE (No CORS allowed)') {
    echo "✓ CORS: Secure\n";
    $securityScore++;
} else {
    echo "❌ CORS: INSECURE - SHOULD FIX BEFORE DEPLOYMENT\n";
}

if ($dirStatus) {
    echo "✓ Directories: Configured correctly\n";
    $securityScore++;
} else {
    echo "⚠ Directories: Permissions need fixing\n";
}

echo "\nSecurity Score: {$securityScore}/{$maxScore}\n\n";

if ($securityScore < $maxScore) {
    echo "⚠ WARNING: Security issues detected!\n";
    echo "Please fix the issues above before deploying to production.\n";
    echo "\nMINIMUM REQUIRED CHANGES:\n";
    echo "1. Set a secure API key in config.php\n";
    echo "2. Configure specific allowed_origins or leave empty\n";
    echo "3. Ensure all directories are writable\n\n";
    exit(1);
} else {
    echo "✓ All security checks passed!\n";
    echo "Your API is ready for deployment.\n\n";
    echo "NEXT STEPS:\n";
    echo "1. Configure your web server (Apache/Nginx)\n";
    echo "2. Install SSL certificate (HTTPS required)\n";
    echo "3. Update WordPress plugin with API endpoint and key\n";
    echo "4. Test with: php test.php [video-url]\n\n";
    exit(0);
}
