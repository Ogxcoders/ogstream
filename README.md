# Video Processing API (Web B)

A standalone PHP REST API that receives video URLs, downloads videos, converts them to multi-bitrate HLS format using FFmpeg, and returns streaming URLs.

## Features

- **REST API Endpoint**: Accepts POST requests with video URLs
- **Video Download**: Downloads videos from any public URL
- **HLS Conversion**: Converts videos to multi-bitrate HLS format (240p, 360p, 480p)
- **FFmpeg Integration**: Uses optimized FFmpeg settings for streaming
- **CORS Support**: Configurable cross-origin resource sharing
- **API Authentication**: Optional API key protection
- **Logging**: Comprehensive logging for debugging
- **Auto Cleanup**: Optional automatic cleanup of old files

## Requirements

- PHP 7.4 or higher
- FFmpeg installed and accessible
- cURL extension enabled
- Apache/Nginx web server
- Sufficient disk space for video storage
- Docker + Coolify (for deployment)

## Installation

### 1. Deploy to VPS

Upload all files to your Web B server:
```
/var/www/web-b/
├── index.php
├── config.php
├── VideoProcessor.php
├── .htaccess
└── README.md
```

### 2. Install FFmpeg

On Ubuntu/Debian:
```bash
sudo apt update
sudo apt install ffmpeg
```

Verify installation:
```bash
ffmpeg -version
```

### 3. Configure Permissions

Create required directories and set permissions:
```bash
cd /var/www/web-b
mkdir -p videos hls logs
chmod 755 videos hls logs
```

### 4. Configure Web Server

#### Apache
Ensure `.htaccess` is in place and `mod_rewrite` is enabled:
```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
sudo systemctl restart apache2
```

#### Nginx
Add this configuration to your server block:
```nginx
location /hls/ {
    alias /var/www/web-b/hls/;
    add_header Access-Control-Allow-Origin *;
    add_header Cache-Control "public, max-age=31536000";
    
    location ~ \.m3u8$ {
        add_header Cache-Control "no-cache";
    }
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 5. Update Configuration

Edit `config.php`:

```php
return [
    // Set your domain
    'base_url' => 'https://web-b.example.com',
    'hls_url_base' => 'https://web-b.example.com/hls',
    
    // Optional: Set API key for authentication
    'api_key' => 'your-secret-api-key-here',
    
    // Verify FFmpeg path
    'ffmpeg_binary' => '/usr/bin/ffmpeg',
    
    // Other settings as needed...
];
```

## Configuration Options

### API Settings
- `api_key`: Optional API key for authentication
- `allowed_origins`: CORS allowed origins (default: `['*']`)

### Directory Settings
- `videos_dir`: Where to store downloaded videos
- `hls_dir`: Where to store HLS output files

### Public URL Settings
- `base_url`: Your Web B domain
- `hls_url_base`: Public URL to access HLS files

### FFmpeg Settings
- `ffmpeg_binary`: Path to FFmpeg binary
- `ffmpeg_timeout`: Maximum processing time in seconds

### HLS Settings
- `hls_time`: Segment duration (6 seconds recommended)
- `hls_list_size`: Number of segments in playlist (0 = all)

### Cleanup Settings
- `cleanup_original`: Delete original videos after processing
- `max_video_age_days`: Delete files older than X days (0 = never)

## API Usage

### Endpoint
```
POST https://web-b.example.com/api/process-video.php
```

### Request Headers
```
Content-Type: application/json
X-API-Key: your-api-key (if configured)
```

### Request Body
```json
{
  "video_url": "https://example.com/path/to/video.mp4",
  "post_id": 123
}
```

### Success Response (200)
```json
{
  "status": "success",
  "message": "Video processed successfully",
  "hls_url": "https://web-b.example.com/hls/video_123_1699012345/master.m3u8",
  "video_id": "video_123_1699012345"
}
```

### Error Response (400/500)
```json
{
  "status": "error",
  "message": "Error description here"
}
```

## HLS Output Format

The API generates multi-bitrate HLS streams:

- **240p**: 200k video bitrate, 250k max
- **360p**: 350k video bitrate, 400k max  
- **480p**: 500k video bitrate, 600k max
- **Audio**: AAC 64k, 44.1kHz

### Output Structure
```
hls/
└── video_123_1699012345/
    ├── master.m3u8          # Master playlist
    ├── stream_0.m3u8        # 240p playlist
    ├── stream_1.m3u8        # 360p playlist
    ├── stream_2.m3u8        # 480p playlist
    ├── stream_0_000.ts      # 240p segments
    ├── stream_1_000.ts      # 360p segments
    └── stream_2_000.ts      # 480p segments
```

## FFmpeg Command

The API uses this optimized FFmpeg command:

```bash
ffmpeg -y -i input.mp4 \
  -filter_complex "[0:v]split=3[v1][v2][v3];[v1]scale=240:-2:flags=lanczos,setsar=1[v1out];[v2]scale=360:-2:flags=lanczos,setsar=1[v2out];[v3]scale=480:-2:flags=lanczos,setsar=1[v3out]" \
  -map "[v1out]" -map 0:a? -map "[v2out]" -map 0:a? -map "[v3out]" -map 0:a? \
  -c:v libx264 -preset faster -c:a aac -b:a 64k -ar 44100 \
  -b:v:0 200k -maxrate:v:0 250k -bufsize:v:0 400k \
  -b:v:1 350k -maxrate:v:1 400k -bufsize:v:1 700k \
  -b:v:2 500k -maxrate:v:2 600k -bufsize:v:2 1000k \
  -var_stream_map "v:0,a:0 v:1,a:1 v:2,a:2" \
  -master_pl_name master.m3u8 \
  -f hls -hls_time 6 -hls_list_size 0 \
  -hls_segment_filename "stream_%v_%03d.ts" \
  "stream_%v.m3u8"
```

## Testing

### Test with cURL
```bash
curl -X POST https://web-b.example.com/api/process-video.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "video_url": "https://example.com/test-video.mp4",
    "post_id": 999
  }'
```

### Test HLS Playback
Use a HLS player to test the generated stream:
- VLC Media Player
- HLS.js demo player
- Safari (native HLS support)

## Monitoring & Logs

Logs are stored in `logs/api.log`:
```bash
tail -f logs/api.log
```

Log entries include:
- Video processing start/completion
- Download status
- FFmpeg execution output
- Errors and warnings

## Troubleshooting

### FFmpeg Not Found
```bash
# Find FFmpeg location
which ffmpeg

# Update config.php with correct path
'ffmpeg_binary' => '/usr/bin/ffmpeg'
```

### Permission Denied
```bash
# Fix directory permissions
chmod 755 videos hls logs
chown www-data:www-data videos hls logs
```

### Video Download Fails
- Check source URL is publicly accessible
- Verify cURL is enabled: `php -m | grep curl`
- Check firewall/security groups allow outbound connections

### FFmpeg Conversion Fails
- Verify FFmpeg is properly installed
- Check source video is valid
- Review logs for FFmpeg error messages
- Ensure sufficient disk space

### CORS Issues
- Update `allowed_origins` in config.php
- Verify `.htaccess` or Nginx configuration
- Check browser console for CORS errors

## Performance Optimization

### Increase PHP Limits
Edit `php.ini`:
```ini
max_execution_time = 600
memory_limit = 512M
upload_max_filesize = 500M
post_max_size = 500M
```

### FFmpeg Preset
Adjust `-preset` for speed vs quality:
- `ultrafast`: Fastest, larger files
- `faster`: Good balance (default)
- `medium`: Better compression, slower

### Enable Caching
For HLS segments, configure browser caching headers (already in `.htaccess`)

## Security Best Practices

1. **Enable API Key**: Set a strong API key in config.php
2. **Restrict Origins**: Set specific allowed origins instead of `*`
3. **HTTPS Only**: Use SSL/TLS for all connections
4. **File Validation**: Validate video files before processing
5. **Rate Limiting**: Implement rate limiting in production
6. **Regular Cleanup**: Enable automatic cleanup of old files

## Deployment with Coolify

1. Create new service in Coolify
2. Set environment type to PHP
3. Upload files or connect Git repository
4. Configure environment variables in Coolify:
   - `API_KEY`
   - `BASE_URL`
   - `HLS_URL_BASE`
5. Ensure FFmpeg is available in Docker container
6. Deploy and test

## Maintenance

### Cleanup Old Videos
Run manually or set up cron:
```php
<?php
require 'config.php';
require 'VideoProcessor.php';
$processor = new VideoProcessor($config);
$processor->cleanupOldVideos();
```

### Cron Job Example
```bash
0 2 * * * php /var/www/web-b/cleanup.php
```

## Support

For issues or questions, check:
1. `logs/api.log` for error messages
2. FFmpeg installation: `ffmpeg -version`
3. PHP configuration: `php -i`
4. Web server error logs

## Changelog

### Version 1.0.0
- Initial release
- Multi-bitrate HLS conversion
- REST API endpoint
- Video download functionality
- FFmpeg integration
- Logging system
- CORS support
