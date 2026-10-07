<?php

namespace App\Services\Media;

use App\Exceptions\UnsupportedPlatformException;
use App\Models\MediaDownload;
use App\Services\Media\Contracts\DownloadResult;
use App\Services\Media\Contracts\MediaExtractorInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MediaService
{
    /**
     * @var array<int, MediaExtractorInterface>
     */
    protected array $extractors;

    public function __construct(
        protected UrlValidator $urlValidator,
        protected PlatformDetector $platformDetector,
        YouTubeService $youTubeService,
        InstagramService $instagramService
    ) {
        $this->extractors = [
            $youTubeService,
            $instagramService,
        ];
    }

    /**
     * Register an additional extractor at runtime (e.g., for tests or plugins).
     */
    public function registerExtractor(MediaExtractorInterface $extractor): void
    {
        array_unshift($this->extractors, $extractor);
    }

    /**
     * Get extractor for a validated URL.
     */
    public function getExtractorForUrl(string $url): MediaExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($url)) {
                return $extractor;
            }
        }

        throw new UnsupportedPlatformException('No compatible media extractor found for this URL.');
    }

    /**
     * Fetch media info and real available formats.
     */
    public function getInfo(string $rawUrl): array
    {
        $normalizedUrl = $this->urlValidator->validateAndNormalize($rawUrl);
        $extractor = $this->getExtractorForUrl($normalizedUrl);
        return $extractor->getInfo($normalizedUrl);
    }

    /**
     * Prepare a media download job record.
     */
    public function createDownloadRecord(string $rawUrl, string $formatId, array $info): MediaDownload
    {
        $normalizedUrl = $this->urlValidator->validateAndNormalize($rawUrl);
        $urlHash = $this->urlValidator->hashUrl($normalizedUrl);
        $extractor = $this->getExtractorForUrl($normalizedUrl);

        $selectedFormat = null;
        foreach ($info['formats'] ?? [] as $f) {
            if ($f['id'] === $formatId) {
                $selectedFormat = $f;
                break;
            }
        }

        $type = $selectedFormat['type'] ?? (str_starts_with($formatId, 'audio') ? 'audio' : 'video');
        $quality = $selectedFormat['quality'] ?? $formatId;
        $extension = $selectedFormat['extension'] ?? ($type === 'audio' ? 'mp3' : 'mp4');

        $ttlHours = (int) config('media.temp_file_ttl_hours', 2);
        $expiresAt = Carbon::now()->addHours($ttlHours);

        return MediaDownload::create([
            'uuid' => (string) Str::uuid(),
            'platform' => $extractor->getPlatform(),
            'source_url_hash' => $urlHash,
            'title' => Str::limit($info['title'] ?? 'Media Download', 250),
            'format_id' => $formatId,
            'format' => $type,
            'quality' => $quality,
            'extension' => $extension,
            'status' => 'queued',
            'progress' => 0,
            'download_token' => Str::random(40),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Execute download directly via extractor.
     */
    public function processDownload(string $rawUrl, string $formatId, ?callable $progressCallback = null): DownloadResult
    {
        $normalizedUrl = $this->urlValidator->validateAndNormalize($rawUrl);
        $extractor = $this->getExtractorForUrl($normalizedUrl);
        return $extractor->prepareDownload($normalizedUrl, $formatId, $progressCallback);
    }

    /**
     * Clean expired files from disk and database.
     *
     * @return array{deleted_files: int, cleaned_records: int}
     */
    public function cleanupExpiredFiles(): array
    {
        $tempDir = config('media.temp_directory', storage_path('app/media_temp'));
        $deletedCount = 0;
        $cleanedRecords = 0;

        $expiredDownloads = MediaDownload::where('expires_at', '<=', Carbon::now())
            ->orWhere(function ($q) {
                $q->where('status', 'failed')
                  ->where('created_at', '<=', Carbon::now()->subHours(6));
            })
            ->get();

        foreach ($expiredDownloads as $download) {
            if ($download->file_path && File::exists($download->file_path)) {
                @unlink($download->file_path);
                $deletedCount++;
            }
            $download->update([
                'file_path' => null,
                'status' => 'failed',
                'error_message' => 'File expired and was removed.',
            ]);
            $cleanedRecords++;
        }

        // Also sweep stray files in temp directory older than TTL
        $ttlHours = (int) config('media.temp_file_ttl_hours', 2);
        $thresholdTime = time() - ($ttlHours * 3600);

        if (File::isDirectory($tempDir)) {
            $files = File::files($tempDir);
            foreach ($files as $file) {
                if ($file->getMTime() < $thresholdTime) {
                    @unlink($file->getRealPath());
                    $deletedCount++;
                }
            }
        }

        return [
            'deleted_files' => $deletedCount,
            'cleaned_records' => $cleanedRecords,
        ];
    }
}
