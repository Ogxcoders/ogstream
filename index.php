<?php
/**
 * Web B Video Processing API Endpoint
 * Receives video URLs from Web A, processes them, and returns HLS URLs
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in response
ini_set('log_errors', 1);

// Set headers
header('Content-Type: application/json');

// Load configuration
$config = require __DIR__ . '/config.php';

// CORS handling
$allowedOrigins = $config['allowed_origins'];
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    header('Access-Control-Max-Age: 86400');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// API Key authentication - REQUIRED for security
if (empty($config['api_key']) || $config['api_key'] === 'ogs327k9mP2xR4wN6vB8qT1yH3zL5jC0fG9aK') {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server configuration error: API key not configured. Please set a secure API key in config.php'
    ]);
    exit;
}

$providedKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

if ($providedKey !== $config['api_key']) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: Invalid or missing API key'
    ]);
    exit;
}

// Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// Validate request
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON in request body'
    ]);
    exit;
}

// Validate required fields
if (empty($data['video_url'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required field: video_url'
    ]);
    exit;
}

$videoUrl = $data['video_url'];
$postId = isset($data['post_id']) ? (int)$data['post_id'] : 0;

// Load VideoProcessor class
require_once __DIR__ . '/VideoProcessor.php';

try {
    // Create processor instance
    $processor = new VideoProcessor($config);
    
    // Process the video
    $result = $processor->processVideo($videoUrl, $postId);
    
    // Return response
    if ($result['status'] === 'success') {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
