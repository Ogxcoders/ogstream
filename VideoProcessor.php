<?php
/**
 * Video Processor Class
 * Handles video download, FFmpeg conversion to HLS, and file management
 */

class VideoProcessor {
    
    private $config;
    private $videosDir;
    private $hlsDir;
    private $logFile;
    
    public function __construct($config) {
        $this->config = $config;
        $this->videosDir = $config['videos_dir'];
        $this->hlsDir = $config['hls_dir'];
        $this->logFile = $config['log_file'];
        
        // Create directories if they don't exist
        $this->ensureDirectories();
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories() {
        $dirs = [
            $this->videosDir,
            $this->hlsDir,
            dirname($this->logFile)
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Process a video URL: download, convert to HLS, return streaming URL
     * 
     * @param string $videoUrl The URL of the video to process
     * @param int $postId The WordPress post ID (for tracking)
     * @return array Result with status, message, and hls_url
     */
    public function processVideo($videoUrl, $postId) {
        try {
            $this->log("Starting video processing for post ID: {$postId}");
            $this->log("Video URL: {$videoUrl}");
            
            // Validate video URL
            if (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid video URL");
            }
            
            // Generate unique identifier for this video
            $videoId = $this->generateVideoId($postId);
            
            // Download the video
            $this->log("Downloading video...");
            $downloadedFile = $this->downloadVideo($videoUrl, $videoId);
            
            if (!$downloadedFile) {
                throw new Exception("Failed to download video");
            }
            
            $this->log("Video downloaded: {$downloadedFile}");
            
            // Convert to HLS
            $this->log("Converting to HLS format...");
            $hlsUrl = $this->convertToHLS($downloadedFile, $videoId);
            
            if (!$hlsUrl) {
                throw new Exception("Failed to convert video to HLS");
            }
            
            $this->log("HLS conversion completed: {$hlsUrl}");
            
            // Cleanup original video if configured
            if ($this->config['cleanup_original']) {
                unlink($downloadedFile);
                $this->log("Original video deleted");
            }
            
            return [
                'status' => 'success',
                'message' => 'Video processed successfully',
                'hls_url' => $hlsUrl,
                'video_id' => $videoId
            ];
            
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage(), 'ERROR');
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'hls_url' => null
            ];
        }
    }
    
    /**
     * Generate unique video ID
     */
    private function generateVideoId($postId) {
        return 'video_' . $postId . '_' . time();
    }
    
    /**
     * Download video from URL
     */
    private function downloadVideo($url, $videoId) {
        // Get file extension from URL
        $extension = $this->getExtensionFromUrl($url);
        $filename = $videoId . '.' . $extension;
        $filepath = $this->videosDir . '/' . $filename;
        
        // Download using cURL
        $ch = curl_init($url);
        $fp = fopen($filepath, 'wb');
        
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        fclose($fp);
        
        if (!$success || $httpCode !== 200) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            return false;
        }
        
        // Verify file was downloaded and has content
        if (!file_exists($filepath) || filesize($filepath) === 0) {
            return false;
        }
        
        return $filepath;
    }
    
    /**
     * Get file extension from URL
     */
    private function getExtensionFromUrl($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        // Default to mp4 if no extension found
        return $extension ?: 'mp4';
    }
    
    /**
     * Convert video to HLS format using FFmpeg
     */
    private function convertToHLS($inputFile, $videoId) {
        // Create output directory for this video
        $outputDir = $this->hlsDir . '/' . $videoId;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Build FFmpeg command
        $command = $this->buildFFmpegCommand($inputFile, $outputDir);
        
        $this->log("Executing FFmpeg command");
        $this->log("Command: " . $command);
        
        // Execute FFmpeg
        $output = [];
        $returnCode = 0;
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        // Log FFmpeg output
        $this->log("FFmpeg output:\n" . implode("\n", $output));
        
        if ($returnCode !== 0) {
            $this->log("FFmpeg failed with return code: {$returnCode}", 'ERROR');
            return false;
        }
        
        // Verify master playlist was created
        $masterPlaylist = $outputDir . '/master.m3u8';
        if (!file_exists($masterPlaylist)) {
            $this->log("Master playlist not found: {$masterPlaylist}", 'ERROR');
            return false;
        }
        
        // Return public URL to master playlist
        return $this->config['hls_url_base'] . '/' . $videoId . '/master.m3u8';
    }
    
    /**
     * Build FFmpeg command for HLS conversion
     */
    private function buildFFmpegCommand($inputFile, $outputDir) {
        $ffmpeg = $this->config['ffmpeg_binary'];
        
        // Escape paths
        $input = escapeshellarg($inputFile);
        $output = $outputDir . '/';
        
        // Build filter complex for multiple resolutions
        $filterComplex = "[0:v]split=3[v1][v2][v3];";
        $filterComplex .= "[v1]scale=240:-2:flags=lanczos,setsar=1[v1out];";
        $filterComplex .= "[v2]scale=360:-2:flags=lanczos,setsar=1[v2out];";
        $filterComplex .= "[v3]scale=480:-2:flags=lanczos,setsar=1[v3out]";
        
        // Build complete command
        $command = "{$ffmpeg} -y -i {$input} ";
        $command .= "-filter_complex \"{$filterComplex}\" ";
        
        // Map streams
        $command .= "-map \"[v1out]\" -map 0:a? ";
        $command .= "-map \"[v2out]\" -map 0:a? ";
        $command .= "-map \"[v3out]\" -map 0:a? ";
        
        // Encoding settings
        $command .= "-c:v libx264 -preset faster ";
        $command .= "-c:a aac -b:a 64k -ar 44100 ";
        
        // Bitrate settings for each resolution
        $command .= "-b:v:0 200k -maxrate:v:0 250k -bufsize:v:0 400k ";
        $command .= "-b:v:1 350k -maxrate:v:1 400k -bufsize:v:1 700k ";
        $command .= "-b:v:2 500k -maxrate:v:2 600k -bufsize:v:2 1000k ";
        
        // HLS settings
        $command .= "-var_stream_map \"v:0,a:0 v:1,a:1 v:2,a:2\" ";
        $command .= "-master_pl_name master.m3u8 ";
        $command .= "-f hls -hls_time {$this->config['hls_time']} ";
        $command .= "-hls_list_size {$this->config['hls_list_size']} ";
        $command .= "-hls_segment_filename \"{$output}stream_%v_%03d.ts\" ";
        $command .= "\"{$output}stream_%v.m3u8\"";
        
        return $command;
    }
    
    /**
     * Log message to file
     */
    private function log($message, $level = 'INFO') {
        if (!$this->config['debug'] && $level === 'INFO') {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Clean up old videos
     */
    public function cleanupOldVideos() {
        $maxAge = $this->config['max_video_age_days'];
        
        if ($maxAge <= 0) {
            return;
        }
        
        $cutoffTime = time() - ($maxAge * 24 * 60 * 60);
        $deleted = 0;
        
        // Clean videos directory
        foreach (glob($this->videosDir . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        // Clean HLS directory
        foreach (glob($this->hlsDir . '/*') as $dir) {
            if (is_dir($dir) && filemtime($dir) < $cutoffTime) {
                $this->deleteDirectory($dir);
                $deleted++;
            }
        }
        
        $this->log("Cleanup completed: {$deleted} items deleted");
    }
    
    /**
     * Recursively delete directory
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
