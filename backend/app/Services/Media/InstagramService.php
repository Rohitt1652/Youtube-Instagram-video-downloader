<?php

namespace App\Services\Media;

use App\Exceptions\ExtractionFailedException;
use App\Exceptions\MediaLimitExceededException;
use App\Exceptions\MediaUnavailableException;
use App\Services\Media\Contracts\DownloadResult;
use App\Services\Media\Contracts\MediaExtractorInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramService implements MediaExtractorInterface
{
    public function __construct(
        protected ProcessExecutor $executor
    ) {}

    public function supports(string $url): bool
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $allowed = config('media.allowed_domains.instagram', [
            'instagram.com', 'www.instagram.com', 'm.instagram.com',
        ]);
        if (!in_array($host, $allowed, true)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        return str_contains($path, '/reel/') || str_contains($path, '/p/') || str_contains($path, '/reels/');
    }

    public function getPlatform(): string
    {
        return 'instagram';
    }

    public function getInfo(string $url): array
    {
        $binaryParts = $this->executor->parseBinary(config('media.binaries.ytdlp', 'python -m yt_dlp'));

        $command = array_merge($binaryParts, [
            '--dump-single-json',
            '--no-warnings',
            '--no-check-certificates',
            '--skip-download',
            $url,
        ]);

        $result = $this->executor->execute($command, config('media.download_timeout', 60));

        if ($result['exit_code'] !== 0) {
            $this->handleInstagramError($result['stderr']);
        }

        $json = json_decode($result['stdout'], true);
        if (!$json || !is_array($json)) {
            throw new ExtractionFailedException('Unable to parse Instagram post metadata.');
        }

        $duration = isset($json['duration']) ? (int) $json['duration'] : null;
        $maxDuration = (int) config('media.max_duration', 1800);
        if ($duration !== null && $duration > $maxDuration) {
            throw new MediaLimitExceededException("Instagram media duration ({$duration}s) exceeds the allowed limit ({$maxDuration}s).");
        }

        $formats = $this->extractInstagramFormats($json);

        return [
            'platform' => 'instagram',
            'title' => (string) ($json['title'] ?? ($json['description'] ?? 'Instagram Media')),
            'thumbnail' => $json['thumbnail'] ?? null,
            'duration' => $duration,
            'author' => $json['uploader'] ?? ($json['channel'] ?? null),
            'formats' => $formats,
        ];
    }

    protected function extractInstagramFormats(array $json): array
    {
        $formats = [];
        $rawFormats = $json['formats'] ?? [];
        $hasVideo = false;
        $hasAudio = false;

        $maxHeight = 0;
        foreach ($rawFormats as $f) {
            $vcodec = $f['vcodec'] ?? 'none';
            $acodec = $f['acodec'] ?? 'none';
            if ($vcodec !== 'none') {
                $hasVideo = true;
                $h = isset($f['height']) ? (int) $f['height'] : 0;
                if ($h > $maxHeight) {
                    $maxHeight = $h;
                }
            }
            if ($acodec !== 'none') {
                $hasAudio = true;
            }
        }

        // If height detected
        $videoQuality = ($maxHeight > 0) ? "{$maxHeight}p" : "Standard HD";

        if ($hasVideo || !empty($json['url'])) {
            $formats[] = [
                'id' => 'video_best',
                'type' => 'video',
                'extension' => 'mp4',
                'quality' => $videoQuality . ' MP4',
                'filesize' => null,
            ];
        }

        if ($hasAudio) {
            $formats[] = [
                'id' => 'audio_mp3',
                'type' => 'audio',
                'extension' => 'mp3',
                'quality' => 'MP3 Audio',
                'filesize' => null,
            ];
        }

        if (empty($formats)) {
            // Default best format fallback if present
            $formats[] = [
                'id' => 'video_best',
                'type' => 'video',
                'extension' => 'mp4',
                'quality' => 'Standard MP4',
                'filesize' => null,
            ];
        }

        return $formats;
    }

    public function prepareDownload(string $url, string $formatId, ?callable $progressCallback = null): DownloadResult
    {
        $tempDir = config('media.temp_directory', storage_path('app/media_temp'));
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $uniquePrefix = 'ig_' . Str::random(12);
        $outputTemplate = $tempDir . DIRECTORY_SEPARATOR . $uniquePrefix . '.%(ext)s';

        $binaryParts = $this->executor->parseBinary(config('media.binaries.ytdlp', 'python -m yt_dlp'));
        $ffmpegBinary = config('media.binaries.ffmpeg', 'ffmpeg');

        $isAudio = str_starts_with($formatId, 'audio_');
        $command = array_merge($binaryParts, [
            '--no-warnings',
            '--no-check-certificates',
            '--ffmpeg-location', $ffmpegBinary,
            '--newline',
        ]);

        if ($isAudio) {
            $command = array_merge($command, [
                '-x',
                '--audio-format', 'mp3',
                '--audio-quality', '0',
                '-o', $outputTemplate,
                $url,
            ]);
        } else {
            $command = array_merge($command, [
                '-f', 'bestvideo+bestaudio/best',
                '--merge-output-format', 'mp4',
                '-o', $outputTemplate,
                $url,
            ]);
        }

        if ($progressCallback) {
            $progressCallback(10, 'Initializing Instagram download...');
        }

        $result = $this->executor->execute($command, config('media.download_timeout', 300), null, function ($type, $line) use ($progressCallback) {
            if ($progressCallback && preg_match('/\[download\]\s+(\d+(?:\.\d+)?)%/', $line, $matches)) {
                $pct = (int) round((float) $matches[1]);
                $mapped = 15 + (int) ($pct * 0.7);
                $progressCallback($mapped, "Downloading Instagram media: {$pct}%");
            } elseif ($progressCallback && str_contains($line, '[Merger]')) {
                $progressCallback(90, 'Processing video stream...');
            }
        });

        if ($result['exit_code'] !== 0) {
            $this->handleInstagramError($result['stderr']);
        }

        $pattern = $tempDir . DIRECTORY_SEPARATOR . $uniquePrefix . '.*';
        $matchingFiles = glob($pattern);

        if (empty($matchingFiles)) {
            throw new ExtractionFailedException('Instagram processing completed but media file was not found.');
        }

        $downloadedFile = $matchingFiles[0];
        $fileSize = (int) filesize($downloadedFile);

        $maxSizeMb = (int) config('media.max_download_size_mb', 500);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;
        if ($fileSize > $maxSizeBytes) {
            @unlink($downloadedFile);
            throw new MediaLimitExceededException("Downloaded file size exceeds {$maxSizeMb}MB limit.");
        }

        $ext = strtolower(pathinfo($downloadedFile, PATHINFO_EXTENSION));
        $mimeType = $isAudio ? 'audio/mpeg' : 'video/mp4';

        if ($progressCallback) {
            $progressCallback(100, 'Processing complete!');
        }

        return new DownloadResult(
            filePath: $downloadedFile,
            fileName: basename($downloadedFile),
            fileSize: $fileSize,
            mimeType: $mimeType,
            extension: $ext,
            format: $isAudio ? 'audio' : 'video',
            quality: $formatId
        );
    }

    protected function handleInstagramError(string $stderr): void
    {
        $lower = strtolower($stderr);

        if (str_contains($lower, 'login') || str_contains($lower, 'sign in') || str_contains($lower, 'checkpoint')) {
            throw new MediaUnavailableException('This Instagram media is private or blocked behind an account login wall.');
        }
        if (str_contains($lower, 'private') || str_contains($lower, 'restricted')) {
            throw new MediaUnavailableException('This Instagram account or post is private.');
        }
        if (str_contains($lower, 'not found') || str_contains($lower, 'does not exist') || str_contains($lower, '404')) {
            throw new MediaUnavailableException('This Instagram post was deleted or does not exist.');
        }

        Log::error('Instagram extraction error: ' . $stderr);
        throw new ExtractionFailedException('Unable to extract Instagram media. The post may be restricted or Instagram has changed access requirements.');
    }
}
