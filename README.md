# MediaGrab 🎬

MediaGrab is a production-quality, secure web application designed for users and engineers to process and download media they own or have permission to save from authorized **YouTube** and **Instagram** URLs.

Built with **Laravel 12**, **React 19**, **Vite**, **Tailwind CSS**, and **FFmpeg**, MediaGrab features rigorous SSRF prevention, input validation, background queue processing, real format discovery, and automated cleanup.

> **Important**: MediaGrab strictly operates on public and authorized media. It does not bypass DRM, age restrictions, private account walls, paywalls, or copyright protections.

---

## 🏗️ Architecture & Features

- **Frontend (`/frontend`)**:
  - React 19 + Vite 6
  - Tailwind CSS with dark-mode aesthetic and glassmorphism styling
  - Axios API client with standardized error handling
  - Real-time client platform detection (YouTube / Instagram badge triggers as you type)
  - Realistic multi-stage progress polling (Preparing ➔ Downloading ➔ Merging ➔ Ready)
  - Browser-local download history with quick re-download links
  - Fully responsive on Mobile, Tablet, and Desktop

- **Backend (`/backend`)**:
  - **Laravel 12** on PHP 8.2+
  - **REST API** with thin controllers and dedicated form requests
  - **Security Core**:
    - Enforced HTTPS only
    - Strict hostname allowlist (`youtube.com`, `m.youtube.com`, `youtu.be`, `instagram.com`, `www.instagram.com`)
    - SSRF prevention with DNS resolution checks blocking private (RFC 1918), loopback, link-local, and cloud metadata (AWS/GCP/Azure) IP addresses
    - Safe execution via Symfony Process argument arrays (no raw shell concatenation)
    - Regex-validated format identifiers (`^[a-zA-Z0-9_-]+$`) to prevent parameter injection
  - **Media Extractor Abstraction**:
    - `MediaExtractorInterface` for pluggable platform extractors
    - `YouTubeService` & `InstagramService`
    - Real available format detection (no fake hardcoded resolutions)
    - FFmpeg audio/video stream merging on the server
  - **Queue System**:
    - Asynchronous jobs via `ProcessMediaDownload`
    - Step-by-step progress percentage tracking
  - **Storage & Lifecycle**:
    - Dedicated private temp directory (`storage/app/media_temp`)
    - Expiring download tokens (never exposes internal server paths)
    - Automated hourly cleanup command (`php artisan media:clean`)

---

## 📋 System Requirements

- **PHP**: 8.2 or 8.3 with `pdo_mysql`, `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`
- **Composer**: 2.x
- **Node.js**: 18+ (tested on v24.15.0) & npm
- **Database**: MySQL 8.0+ or MariaDB 10.4+
- **Media Tools**:
  - **FFmpeg**: Version 4.x - 9.x in system PATH
  - **Python**: 3.9+ with `yt-dlp` (`python -m pip install yt-dlp`)

---

## 🚀 Local Development Setup

### 1. Database Setup
Ensure MySQL is running (e.g. through XAMPP or native service).
Create the database:
```sql
CREATE DATABASE mediagrab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend Setup
Navigate to the `backend/` directory:
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Verify your `.env` contains correct database and binary configurations:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mediagrab
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

MAX_MEDIA_DURATION=1800
MAX_DOWNLOAD_SIZE_MB=500
DOWNLOAD_TIMEOUT=300
TEMP_FILE_TTL_HOURS=2

YTDLP_BINARY="python -m yt_dlp"
FFMPEG_BINARY="ffmpeg"
```

Start the background queue worker (in a separate terminal):
```bash
php artisan queue:work
```

Start the backend development server:
```bash
php artisan serve --port=8000
```
Backend API will be accessible at: `http://localhost:8000`

---

### 3. Frontend Setup
Navigate to the `frontend/` directory:
```bash
cd frontend
npm install
npm run dev
```
Frontend development server will be running at: `http://localhost:5173`

---

## 🧪 Testing

### Backend Automated Tests
Execute the full PHPUnit test suite:
```bash
cd backend
php artisan test
```
**Test Coverage Includes:**
- Unit tests: `UrlValidatorTest`, `PlatformDetectorTest`
- Feature tests: `MediaApiTest` (Info endpoint, download creation, streaming, invalid formats, expired tokens)
- Security tests: SSRF mitigation, injection protection, insecure protocol rejection
- Rate limiting tests: `RateLimitTest` (Throttling on 429 status code)
- Cleanup tests: Stale file and record deletion

### Frontend Automated Tests
Execute Vitest test suite:
```bash
cd frontend
npm test
```
**Test Coverage Includes:**
- URL input and HTTPS validation
- Loading skeleton states
- Successful preview card rendering and format selection
- API error notification display
- Download progress modal and percentage display

### Production Frontend Build
```bash
cd frontend
npm run build
```

### Manual Verification Script
Run the built-in manual verification script:
```bash
cd backend
php manual_test.php [optional_live_media_url]
```

---

## 📡 API Specification

### 1. Retrieve Media Information
- **Endpoint**: `POST /api/media/info`
- **Rate Limit**: 30 requests / minute / IP
- **Request Body**:
```json
{
  "url": "https://www.youtube.com/watch?v=aqz-KE-bpKQ"
}
```
- **Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "platform": "youtube",
    "title": "Creative Commons Sample",
    "thumbnail": "https://...",
    "duration": 60,
    "author": "Blender Foundation",
    "formats": [
      {
        "id": "video_1080p",
        "type": "video",
        "extension": "mp4",
        "quality": "1080p",
        "filesize": 15420190
      },
      {
        "id": "video_720p",
        "type": "video",
        "extension": "mp4",
        "quality": "720p",
        "filesize": 9510200
      },
      {
        "id": "audio_mp3",
        "type": "audio",
        "extension": "mp3",
        "quality": "MP3 Audio (192kbps)",
        "filesize": null
      }
    ]
  }
}
```

### 2. Initiate Download Job
- **Endpoint**: `POST /api/media/download`
- **Rate Limit**: 10 requests / minute / IP
- **Request Body**:
```json
{
  "url": "https://www.youtube.com/watch?v=aqz-KE-bpKQ",
  "format_id": "video_720p"
}
```
- **Response (202 Accepted)**:
```json
{
  "success": true,
  "job_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d"
}
```

### 3. Check Job Status
- **Endpoint**: `GET /api/media/jobs/{id}`
- **Response (Processing)**:
```json
{
  "success": true,
  "data": {
    "status": "processing",
    "progress": 65
  }
}
```
- **Response (Completed)**:
```json
{
  "success": true,
  "data": {
    "status": "completed",
    "progress": 100,
    "title": "Creative Commons Sample",
    "format": "video",
    "quality": "720p",
    "file_size": 9510200,
    "download_url": "http://localhost:8000/api/media/file/7qA9B...token...",
    "expires_at": "2026-10-06T14:48:00Z"
  }
}
```

### 4. Download / Stream Media File
- **Endpoint**: `GET /api/media/file/{token}`
- **Headers**:
  - `Content-Type: video/mp4`
  - `Content-Disposition: attachment; filename="Creative Commons Sample.mp4"`
  - `Content-Length: 9510200`

### 5. Health Check
- **Endpoint**: `GET /api/media/health`
- **Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "app_name": "MediaGrab",
    "php_version": "8.2.12",
    "temp_dir_writable": true
  }
}
```

---

## 🧹 Scheduled Tasks & Temporary File Cleanup

MediaGrab stores processed files in `storage/app/media_temp`. Files older than `TEMP_FILE_TTL_HOURS` (default: 2 hours) are purged automatically.

### Running Cleanup Manually
```bash
php artisan media:clean
```

### Running Laravel Scheduler
Add to system crontab (`crontab -e`):
```bash
* * * * * cd /path/to/mediagrab/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🌐 Production Deployment (Ubuntu 22.04 / 24.04 + Nginx)

### Server Requirements
- **Hardware**: Minimum 2 vCPU, 2 GB RAM (FFmpeg stream merging requires CPU and memory). Shared hosting is not recommended.
- **Packages**:
```bash
sudo apt update
sudo apt install -y nginx php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring \
    php8.2-xml php8.2-zip ffmpeg python3 python3-pip supervisor
pip3 install yt-dlp --break-system-packages
```

### Nginx Configuration
Create `/etc/nginx/sites-available/mediagrab`:
```nginx
server {
    listen 80;
    server_name mediagrab.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name mediagrab.example.com;

    ssl_certificate /etc/letsencrypt/live/mediagrab.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mediagrab.example.com/privkey.pem;

    client_max_body_size 50M;

    # Serve built React frontend
    root /var/www/mediagrab/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Proxy backend API requests
    location /api {
        root /var/www/mediagrab/backend/public;
        try_files $uri $uri/ /index.php?$query_string;

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/mediagrab/backend/public$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }
    }
}
```

### Supervisor Queue Worker
Create `/etc/supervisor/conf.d/mediagrab-worker.conf`:
```ini
[program:mediagrab-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mediagrab/backend/artisan queue:work --sleep=3 --tries=1 --timeout=360
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/mediagrab-worker.log
stopwaitsecs=360
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mediagrab-worker:*
```

### File Permissions
```bash
sudo chown -R www-data:www-data /var/www/mediagrab/backend/storage /var/www/mediagrab/backend/bootstrap/cache
sudo chmod -R 775 /var/www/mediagrab/backend/storage /var/www/mediagrab/backend/bootstrap/cache
```

---

## 🔒 Security Summary

1. **Strict URL Allowlist**: Only explicitly listed YouTube and Instagram hosts accepted.
2. **SSRF Hardening**: IP addresses in hostnames and resolved internal network ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, 169.254.169.254) are rejected before any backend socket or extractor executes.
3. **Command Injection Prevention**: Process commands are structured strictly as arguments arrays through `Symfony\Component\Process\Process`. Raw shell string concatenation (`shell_exec`) is prohibited.
4. **Content-Length & Duration Enforcement**: Enforces `MAX_MEDIA_DURATION` and `MAX_DOWNLOAD_SIZE_MB`.
5. **Rate Limiting**: Built-in IP throttling returns HTTP 429 when abuse limits are exceeded.
6. **No Secret Leaks**: Stack traces are suppressed in production mode and URLs are sanitized and hashed.
