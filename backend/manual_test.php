<?php

/**
 * MediaGrab Manual Integration Verification Script
 *
 * Usage: php manual_test.php [optional_url]
 *
 * Tests the MediaGrab backend live logic safely with authorized content URLs.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Media\MediaService;
use App\Services\Media\UrlValidator;
use App\Services\Media\PlatformDetector;

echo "=========================================================\n";
echo "      MediaGrab Manual Integration Verification Script   \n";
echo "=========================================================\n\n";

$validator = app(UrlValidator::class);
$detector = app(PlatformDetector::class);
$mediaService = app(MediaService::class);

$testCases = [
    [
        'name' => 'Valid YouTube URL syntax & platform detection',
        'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', // Creative Commons Big Buck Bunny
        'expect_pass' => true,
    ],
    [
        'name' => 'Valid YouTube Short URL syntax',
        'url' => 'https://www.youtube.com/shorts/aqz-KE-bpKQ',
        'expect_pass' => true,
    ],
    [
        'name' => 'Valid Instagram Reel URL syntax',
        'url' => 'https://www.instagram.com/reel/C1234567/',
        'expect_pass' => true,
    ],
    [
        'name' => 'Insecure HTTP rejection (Security)',
        'url' => 'http://www.youtube.com/watch?v=aqz-KE-bpKQ',
        'expect_pass' => false,
    ],
    [
        'name' => 'Unsupported domain rejection (Security)',
        'url' => 'https://vimeo.com/76979871',
        'expect_pass' => false,
    ],
    [
        'name' => 'SSRF Loopback rejection (Security)',
        'url' => 'https://127.0.0.1/admin',
        'expect_pass' => false,
    ],
    [
        'name' => 'SSRF Cloud metadata rejection (Security)',
        'url' => 'https://169.254.169.254/latest/meta-data/',
        'expect_pass' => false,
    ],
    [
        'name' => 'Command injection rejection (Security)',
        'url' => 'https://www.youtube.com/watch?v=123;rm -rf /',
        'expect_pass' => false,
    ],
];

echo "Phase 1: Validating URL security rules & platform detection:\n";
echo "---------------------------------------------------------\n";

foreach ($testCases as $tc) {
    try {
        $normalized = $validator->validateAndNormalize($tc['url']);
        $detected = $detector->detect($normalized);
        if ($tc['expect_pass']) {
            echo "[PASS] {$tc['name']}\n       Platform: {$detected['platform']} ({$detected['content_type']})\n";
        } else {
            echo "[FAIL] Expected rejection but passed: {$tc['name']}\n";
        }
    } catch (\Throwable $e) {
        if (!$tc['expect_pass']) {
            echo "[PASS] {$tc['name']}\n       Properly rejected: " . $e->getMessage() . "\n";
        } else {
            echo "[FAIL] Unexpected error on valid URL: {$tc['name']}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nPhase 2: Checking System Environment & Binaries:\n";
echo "---------------------------------------------------------\n";

$tempDir = config('media.temp_directory');
echo "- Temp storage directory: {$tempDir}\n";
echo "  Writable: " . (is_writable(dirname($tempDir)) || is_writable($tempDir) ? 'YES' : 'NO') . "\n";

$ytdlpBinary = config('media.binaries.ytdlp');
echo "- yt-dlp binary: {$ytdlpBinary}\n";

$ffmpegBinary = config('media.binaries.ffmpeg');
echo "- ffmpeg binary: {$ffmpegBinary}\n";

// Optional live fetch test if custom URL provided
$customUrl = $argv[1] ?? null;
if ($customUrl) {
    echo "\nPhase 3: Live Media Extraction Test for: {$customUrl}\n";
    echo "---------------------------------------------------------\n";
    try {
        $info = $mediaService->getInfo($customUrl);
        echo "Successfully retrieved metadata:\n";
        echo "Title:    " . $info['title'] . "\n";
        echo "Platform: " . $info['platform'] . "\n";
        echo "Duration: " . ($info['duration'] ?? 'N/A') . " seconds\n";
        echo "Author:   " . ($info['author'] ?? 'N/A') . "\n";
        echo "Formats available: " . count($info['formats']) . "\n";
        foreach (array_slice($info['formats'], 0, 5) as $fmt) {
            echo " - [{$fmt['id']}] {$fmt['type']} {$fmt['quality']} ({$fmt['extension']})\n";
        }
    } catch (\Throwable $e) {
        echo "Extraction result: " . $e->getMessage() . "\n";
    }
}

echo "\nVerification script completed.\n";
