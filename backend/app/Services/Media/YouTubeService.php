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

class YouTubeService implements MediaExtractorInterface
{
    public function __construct(
        protected ProcessExecutor $executor
    ) {}

    public function supports(string $url): bool
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $allowed = config('media.allowed_domains.youtube', [
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be',
        ]);
        return in_array($host, $allowed, true);
    }

    public function getPlatform(): string
    {
        return 'youtube';
    }

    protected function getCookieArgs(): array
    {
        $cookieFile = storage_path('app/cookies.txt');

        $envCookies = env('YTDLP_COOKIES');
        if (!empty($envCookies)) {
            $dir = dirname($cookieFile);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            File::put($cookieFile, $envCookies);
        }

        if (File::exists($cookieFile) && filesize($cookieFile) > 0) {
            return ['--cookies', $cookieFile];
        }

        return [];
    }

    public function getInfo(string $url): array
    {
        $binaryParts = $this->executor->parseBinary(config('media.binaries.ytdlp', 'yt-dlp'));

        $command = array_merge($binaryParts, [
            '--dump-single-json',
            '--no-warnings',
            '--no-check-certificates',
            '--no-playlist',
            '--skip-download',
            '--force-ipv4',
        ], $this->getCookieArgs(), [
            $url,
        ]);

        $result = $this->executor->execute($command, config('media.download_timeout', 60));

        if ($result['exit_code'] !== 0) {
            $this->handleYtDlpError($result['stderr']);
        }

        $json = json_decode($result['stdout'], true);
        if (!$json || !is_array($json)) {
            throw new ExtractionFailedException('Failed to parse media metadata from YouTube.');
        }

        if (!empty($json['is_live'])) {
            throw new MediaUnavailableException('Live streams cannot be processed.');
        }

        $duration = isset($json['duration']) ? (int) $json['duration'] : null;
        $maxDuration = (int) config('media.max_duration', 1800);
        if ($duration !== null && $duration > $maxDuration) {
            throw new MediaLimitExceededException("Video duration ({$duration}s) exceeds the maximum allowed limit ({$maxDuration}s).");
        }

        $formats = $this->extractAvailableFormats($json);

        return [
            'platform' => 'youtube',
            'title' => (string) ($json['title'] ?? 'YouTube Video'),
            'thumbnail' => $json['thumbnail'] ?? null,
            'duration' => $duration,
            'author' => $json['uploader'] ?? ($json['channel'] ?? null),
            'formats' => $formats,
        ];
    }

    /**
     * Inspect raw yt-dlp formats and extract real available video resolutions and audio options.
     * Do NOT hardcode fake formats.
     */
    protected function extractAvailableFormats(array $json): array
    {
        $rawFormats = $json['formats'] ?? [];
        $hasAudio = false;
        $availableHeights = [];

        foreach ($rawFormats as $f) {
            $vcodec = $f['vcodec'] ?? 'none';
            $acodec = $f['acodec'] ?? 'none';
            $height = isset($f['height']) ? (int) $f['height'] : 0;

            if ($acodec !== 'none') {
                $hasAudio = true;
            }

            if ($vcodec !== 'none' && $height > 0) {
                $availableHeights[$height] = true;
            }
        }

        $targetResolutions = [
            1080 => '1080p',
            720  => '720p',
            480  => '480p',
            360  => '360p',
            240  => '240p',
        ];

        $outputFormats = [];

        // Check each standard resolution: only include if the source actually has a stream at or above this height
        foreach ($targetResolutions as $resHeight => $resLabel) {
            $matched = false;
            foreach (array_keys($availableHeights) as $h) {
                if ($h >= $resHeight) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                // Find approx filesize if available
                $filesize = null;
                foreach ($rawFormats as $f) {
                    if (($f['height'] ?? 0) === $resHeight) {
                        $filesize = $f['filesize'] ?? ($f['filesize_approx'] ?? null);
                        if ($filesize) break;
                    }
                }

                $outputFormats[] = [
                    'id' => "video_{$resLabel}",
                    'type' => 'video',
                    'extension' => 'mp4',
                    'quality' => $resLabel,
                    'filesize' => $filesize ? (int) $filesize : null,
                ];
            }
        }

        // If no target resolutions matched but video exists, take max available height
        if (empty($outputFormats) && !empty($availableHeights)) {
            $maxHeight = max(array_keys($availableHeights));
            $label = "{$maxHeight}p";
            $outputFormats[] = [
                'id' => "video_{$label}",
                'type' => 'video',
                'extension' => 'mp4',
                'quality' => $label,
                'filesize' => null,
            ];
        }

        // Audio options if audio is present
        if ($hasAudio) {
            $outputFormats[] = [
                'id' => 'audio_mp3',
                'type' => 'audio',
                'extension' => 'mp3',
                'quality' => 'MP3 Audio (192kbps)',
                'filesize' => null,
            ];
            $outputFormats[] = [
                'id' => 'audio_m4a',
                'type' => 'audio',
                'extension' => 'm4a',
                'quality' => 'M4A Audio (AAC)',
                'filesize' => null,
            ];
        }

        return $outputFormats;
    }

    public function prepareDownload(string $url, string $formatId, ?callable $progressCallback = null): DownloadResult
    {
        $tempDir = config('media.temp_directory', storage_path('app/media_temp'));
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $uniquePrefix = 'yt_' . Str::random(12);
        $outputTemplate = $tempDir . DIRECTORY_SEPARATOR . $uniquePrefix . '.%(ext)s';

        $binaryParts = $this->executor->parseBinary(config('media.binaries.ytdlp', 'yt-dlp'));
        $ffmpegBinary = config('media.binaries.ffmpeg', 'ffmpeg');

        $command = array_merge($binaryParts, [
            '--no-warnings',
            '--no-check-certificates',
            '--no-playlist',
            '--force-ipv4',
            '--newline', // Output line-by-line progress
            '--ffmpeg-location', $ffmpegBinary,
        ], $this->getCookieArgs());

        $isAudio = str_starts_with($formatId, 'audio_');
        $expectedExt = 'mp4';

        if ($isAudio) {
            $audioFormat = str_replace('audio_', '', $formatId);
            $expectedExt = ($audioFormat === 'm4a') ? 'm4a' : 'mp3';

            $command = array_merge($command, [
                '-x',
                '--audio-format', $expectedExt,
                '--audio-quality', '0',
                '-o', $outputTemplate,
                $url,
            ]);
        } else {
            // Video format
            $height = 720;
            if (preg_match('/video_(\d+)p/', $formatId, $m)) {
                $height = (int) $m[1];
            }

            $formatSelector = "bestvideo[height<={$height}][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<={$height}]+bestaudio/best[height<={$height}]/best";

            $command = array_merge($command, [
                '-f', $formatSelector,
                '--merge-output-format', 'mp4',
                '-o', $outputTemplate,
                $url,
            ]);
        }

        if ($progressCallback) {
            $progressCallback(10, 'Initializing download...');
        }

        $result = $this->executor->execute($command, config('media.download_timeout', 300), null, function ($type, $line) use ($progressCallback) {
            if ($progressCallback && preg_match('/\[download\]\s+(\d+(?:\.\d+)?)%/', $line, $matches)) {
                $pct = (int) round((float) $matches[1]);
                // Map download phase to 15% - 85%
                $mappedProgress = 15 + (int) ($pct * 0.7);
                $progressCallback($mappedProgress, "Downloading source media: {$pct}%");
            } elseif ($progressCallback && str_contains($line, '[Merger]')) {
                $progressCallback(90, 'Merging video and audio streams...');
            } elseif ($progressCallback && str_contains($line, '[ExtractAudio]')) {
                $progressCallback(90, 'Extracting audio stream...');
            }
        });

        if ($result['exit_code'] !== 0) {
            $this->handleYtDlpError($result['stderr']);
        }

        // Find created file
        $pattern = $tempDir . DIRECTORY_SEPARATOR . $uniquePrefix . '.*';
        $matchingFiles = glob($pattern);

        if (empty($matchingFiles)) {
            throw new ExtractionFailedException('Media processing completed but resulting file was not found.');
        }

        $downloadedFile = $matchingFiles[0];
        $fileSize = (int) filesize($downloadedFile);

        $maxSizeMb = (int) config('media.max_download_size_mb', 500);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;
        if ($fileSize > $maxSizeBytes) {
            @unlink($downloadedFile);
            throw new MediaLimitExceededException("Downloaded file size ({$fileSize} bytes) exceeds the {$maxSizeMb}MB limit.");
        }

        $ext = strtolower(pathinfo($downloadedFile, PATHINFO_EXTENSION));
        $mimeType = $isAudio ? ($ext === 'mp3' ? 'audio/mpeg' : 'audio/mp4') : 'video/mp4';

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

    /**
     * Map common yt-dlp error output to user-friendly exceptions.
     */
    protected function handleYtDlpError(string $stderr): void
    {
        $lower = strtolower($stderr);

        if (str_contains($lower, 'private video') || str_contains($lower, 'this video is private')) {
            throw new MediaUnavailableException('This video is private and cannot be downloaded.');
        }
        if (str_contains($lower, "sign in to confirm you're not a bot") || str_contains($lower, "confirm you're not a bot") || (str_contains($lower, 'sign in') && str_contains($lower, 'bot'))) {
            throw new MediaUnavailableException('YouTube bot protection triggered on cloud server. Please provide cookies (YTDLP_COOKIES) or try another video.');
        }
        if (str_contains($lower, 'sign in') || str_contains($lower, 'age-restricted') || str_contains($lower, 'confirm your age')) {
            throw new MediaUnavailableException('This video is age-restricted or requires authentication.');
        }
        if (str_contains($lower, 'members-only') || str_contains($lower, 'join this channel')) {
            throw new MediaUnavailableException('This video is restricted to channel members.');
        }
        if (str_contains($lower, 'video unavailable') || str_contains($lower, 'has been removed') || str_contains($lower, 'does not exist')) {
            throw new MediaUnavailableException('This video is unavailable or has been removed.');
        }
        if (str_contains($lower, 'copyright') || str_contains($lower, 'drm')) {
            throw new MediaUnavailableException('This content is protected by access controls or copyright restrictions.');
        }

        Log::error('YouTube extraction failure: ' . $stderr);
        throw new ExtractionFailedException('Unable to process this YouTube URL. Please verify the URL is public and authorized.');
    }
}
