<?php

namespace App\Jobs;

use App\Models\MediaDownload;
use App\Services\Media\MediaService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMediaDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout for the queue worker running this job (in seconds).
     */
    public int $timeout = 360;

    /**
     * Number of attempts.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $mediaDownloadId,
        public readonly string $mediaUrl,
        public readonly string $formatId
    ) {}

    public function handle(MediaService $mediaService): void
    {
        $download = MediaDownload::find($this->mediaDownloadId);
        if (!$download) {
            Log::warning("ProcessMediaDownload job: MediaDownload record #{$this->mediaDownloadId} not found.");
            return;
        }

        $startTime = microtime(true);
        Log::info("Starting media download job #{$download->id} [UUID: {$download->uuid}] for platform: {$download->platform}");

        try {
            $download->markProcessing(10);

            $result = $mediaService->processDownload(
                $this->mediaUrl,
                $this->formatId,
                function (int $progress, string $statusMessage) use ($download) {
                    $download->markProgress($progress);
                    Log::debug("Download #{$download->id} progress: {$progress}% - {$statusMessage}");
                }
            );

            $ttlHours = (int) config('media.temp_file_ttl_hours', 2);
            $expiresAt = Carbon::now()->addHours($ttlHours);

            $download->markCompleted(
                filePath: $result->filePath,
                fileName: $result->fileName,
                fileSize: $result->fileSize,
                mimeType: $result->mimeType,
                expiresAt: $expiresAt
            );

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("Completed media download job #{$download->id} in {$duration}s. Size: {$result->fileSize} bytes.");

        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $errorMessage = $e->getMessage();

            Log::error("Failed media download job #{$download->id} after {$duration}s. Error: {$errorMessage}", [
                'exception' => get_class($e),
                'platform' => $download->platform,
            ]);

            $download->markFailed($errorMessage);
        }
    }

    /**
     * Handle job failure when max tries or unhandled exception occurs.
     */
    public function failed(\Throwable $exception): void
    {
        $download = MediaDownload::find($this->mediaDownloadId);
        if ($download) {
            $download->markFailed($exception->getMessage());
        }
    }
}
