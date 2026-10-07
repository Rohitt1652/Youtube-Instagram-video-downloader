<?php

namespace App\Http\Controllers;

use App\Exceptions\MediaGrabException;
use App\Http\Requests\MediaDownloadRequest;
use App\Http\Requests\MediaInfoRequest;
use App\Jobs\ProcessMediaDownload;
use App\Models\MediaDownload;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(
        protected MediaService $mediaService
    ) {}

    /**
     * POST /api/media/info
     * Fetch metadata and available formats for a valid media URL.
     */
    public function getInfo(MediaInfoRequest $request): JsonResponse
    {
        $url = $request->validated('url');

        try {
            $info = $this->mediaService->getInfo($url);

            return response()->json([
                'success' => true,
                'data' => $info,
            ]);
        } catch (MediaGrabException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('MediaController::getInfo unexpected error: ' . $e->getMessage(), [
                'url' => $url,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'An error occurred while inspecting the media URL.',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * POST /api/media/download
     * Create a download record and dispatch the processing queue job.
     */
    public function createDownload(MediaDownloadRequest $request): JsonResponse
    {
        $url = $request->validated('url');
        $formatId = $request->validated('format_id');

        try {
            // Retrieve media metadata to ensure format exists and obtain title
            $info = $this->mediaService->getInfo($url);

            $formatExists = false;
            foreach ($info['formats'] ?? [] as $fmt) {
                if ($fmt['id'] === $formatId) {
                    $formatExists = true;
                    break;
                }
            }

            if (!$formatExists) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_FORMAT',
                        'message' => 'The selected download format is not available for this media.',
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Create media_downloads database record
            $downloadRecord = $this->mediaService->createDownloadRecord($url, $formatId, $info);

            // Dispatch download job to queue
            ProcessMediaDownload::dispatch($downloadRecord->id, $url, $formatId);

            return response()->json([
                'success' => true,
                'job_id' => $downloadRecord->uuid,
            ], Response::HTTP_ACCEPTED);

        } catch (MediaGrabException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('MediaController::createDownload exception: ' . $e->getMessage(), [
                'url' => $url,
                'format_id' => $formatId,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_INITIALIZATION_FAILED',
                    'message' => 'Could not initiate media download process.',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/media/jobs/{id}
     * Check status and progress of a download job.
     */
    public function getJobStatus(string $id): JsonResponse
    {
        $download = MediaDownload::where('uuid', $id)->first();

        if (!$download) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'JOB_NOT_FOUND',
                    'message' => 'Download job not found.',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        if ($download->isExpired()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_EXPIRED',
                    'message' => 'This downloaded media file has expired.',
                ],
            ], Response::HTTP_GONE);
        }

        if ($download->isFailed()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PROCESSING_FAILED',
                    'message' => $download->error_message ?: 'Media processing failed.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $responseData = [
            'status' => $download->status,
            'progress' => $download->progress,
        ];

        if ($download->isCompleted()) {
            $responseData['download_url'] = url("/api/media/file/{$download->download_token}");
            $responseData['title'] = $download->title;
            $responseData['format'] = $download->format;
            $responseData['quality'] = $download->quality;
            $responseData['file_size'] = $download->file_size;
            $responseData['expires_at'] = $download->expires_at?->toIso8601String();
        }

        return response()->json([
            'success' => true,
            'data' => $responseData,
        ]);
    }

    /**
     * GET /api/media/file/{token}
     * Safely stream the prepared media file to the user's browser.
     */
    public function downloadFile(string $token): BinaryFileResponse|JsonResponse
    {
        // Enforce token format
        if (!preg_match('/^[a-zA-Z0-9]{30,70}$/', $token)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Invalid download token format.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $download = MediaDownload::where('download_token', $token)->first();

        if (!$download) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FILE_NOT_FOUND',
                    'message' => 'Requested media download token does not exist.',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        if ($download->isExpired()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_EXPIRED',
                    'message' => 'This download link has expired.',
                ],
            ], Response::HTTP_GONE);
        }

        if (!$download->isCompleted() || empty($download->file_path)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FILE_NOT_READY',
                    'message' => 'The file has not finished processing yet.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!File::exists($download->file_path)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FILE_MISSING',
                    'message' => 'The physical media file is missing or has been cleaned up.',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        // Sanitize download filename for the browser
        $cleanTitle = preg_replace('/[^\w\s\-\.\(\)]/u', '_', $download->title) ?: 'media';
        $cleanTitle = trim(preg_replace('/\s+/', ' ', $cleanTitle));
        $ext = $download->extension ?: 'mp4';
        $downloadName = "{$cleanTitle}.{$ext}";

        $headers = [
            'Content-Type' => $download->mime_type ?: 'application/octet-stream',
            'Content-Length' => $download->file_size ?: filesize($download->file_path),
            'Content-Disposition' => "attachment; filename=\"{$downloadName}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->download($download->file_path, $downloadName, $headers);
    }

    /**
     * GET /api/media/health
     * Health check endpoint for system status.
     */
    public function health(): JsonResponse
    {
        $tempDir = config('media.temp_directory', storage_path('app/media_temp'));

        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'app_name' => config('app.name'),
                'php_version' => PHP_VERSION,
                'temp_dir_writable' => is_writable(dirname($tempDir)) || is_writable($tempDir),
                'max_duration' => config('media.max_duration'),
                'max_download_size_mb' => config('media.max_download_size_mb'),
            ],
        ]);
    }
}
