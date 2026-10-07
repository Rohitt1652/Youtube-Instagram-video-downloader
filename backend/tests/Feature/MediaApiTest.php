<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaDownload;
use App\Models\MediaDownload;
use App\Services\Media\Contracts\DownloadResult;
use App\Services\Media\Contracts\MediaExtractorInterface;
use App\Services\Media\MediaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a FakeExtractor into MediaService for reliable automated tests
        $fakeExtractor = new class implements MediaExtractorInterface {
            public function supports(string $url): bool {
                return str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be') || str_contains($url, 'instagram.com');
            }
            public function getPlatform(): string {
                return 'youtube';
            }
            public function getInfo(string $url): array {
                return [
                    'platform' => 'youtube',
                    'title' => 'Sample Authorized Video',
                    'thumbnail' => 'https://example.com/thumb.jpg',
                    'duration' => 180,
                    'author' => 'Test Channel',
                    'formats' => [
                        [
                            'id' => 'video_1080p',
                            'type' => 'video',
                            'extension' => 'mp4',
                            'quality' => '1080p',
                            'filesize' => 12000000,
                        ],
                        [
                            'id' => 'video_720p',
                            'type' => 'video',
                            'extension' => 'mp4',
                            'quality' => '720p',
                            'filesize' => 8000000,
                        ],
                        [
                            'id' => 'audio_mp3',
                            'type' => 'audio',
                            'extension' => 'mp3',
                            'quality' => 'MP3 Audio (192kbps)',
                            'filesize' => null,
                        ],
                    ],
                ];
            }
            public function prepareDownload(string $url, string $formatId, ?callable $progressCallback = null): DownloadResult {
                $tempPath = storage_path('app/media_temp/test_sample.mp4');
                File::ensureDirectoryExists(dirname($tempPath));
                file_put_contents($tempPath, 'dummy video binary content');

                return new DownloadResult(
                    filePath: $tempPath,
                    fileName: 'test_sample.mp4',
                    fileSize: strlen('dummy video binary content'),
                    mimeType: 'video/mp4',
                    extension: 'mp4',
                    format: 'video',
                    quality: $formatId
                );
            }
        };

        $mediaService = $this->app->make(MediaService::class);
        $mediaService->registerExtractor($fakeExtractor);
        $this->app->instance(MediaService::class, $mediaService);
    }

    public function test_health_check_returns_healthy(): void
    {
        $response = $this->getJson('/api/media/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                ],
            ]);
    }

    public function test_media_info_endpoint_returns_metadata_and_formats(): void
    {
        $response = $this->postJson('/api/media/info', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'platform' => 'youtube',
                    'title' => 'Sample Authorized Video',
                    'duration' => 180,
                    'formats' => [
                        [
                            'id' => 'video_1080p',
                            'quality' => '1080p',
                        ],
                    ],
                ],
            ]);
    }

    public function test_media_info_rejects_empty_url(): void
    {
        $response = $this->postJson('/api/media/info', [
            'url' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_media_info_rejects_insecure_http_url(): void
    {
        $response = $this->postJson('/api/media/info', [
            'url' => 'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_media_info_rejects_unsupported_domain(): void
    {
        $response = $this->postJson('/api/media/info', [
            'url' => 'https://dailymotion.com/video/x12345',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_URL',
                ],
            ]);
    }

    public function test_media_info_rejects_ssrf_ip(): void
    {
        $response = $this->postJson('/api/media/info', [
            'url' => 'https://127.0.0.1/video',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'SECURITY_VIOLATION',
                ],
            ]);
    }

    public function test_create_download_accepts_valid_format_and_pushes_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/media/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'format_id' => 'video_1080p',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['success', 'job_id']);

        $jobId = $response->json('job_id');
        $this->assertDatabaseHas('media_downloads', [
            'uuid' => $jobId,
            'format_id' => 'video_1080p',
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessMediaDownload::class);
    }

    public function test_create_download_runs_synchronously_to_completion(): void
    {
        $response = $this->postJson('/api/media/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'format_id' => 'video_1080p',
        ]);

        $response->assertStatus(202);
        $jobId = $response->json('job_id');

        $this->assertDatabaseHas('media_downloads', [
            'uuid' => $jobId,
            'format_id' => 'video_1080p',
            'status' => 'completed',
        ]);
    }

    public function test_create_download_rejects_invalid_format_injection(): void
    {
        $response = $this->postJson('/api/media/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'format_id' => 'video_1080p; rm -rf /',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_create_download_rejects_unavailable_format(): void
    {
        $response = $this->postJson('/api/media/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'format_id' => 'video_4k_ultra',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_FORMAT',
                ],
            ]);
    }

    public function test_get_job_status_returns_queued_processing_or_completed(): void
    {
        $download = MediaDownload::create([
            'uuid' => (string) Str::uuid(),
            'platform' => 'youtube',
            'source_url_hash' => hash('sha256', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
            'title' => 'Test Video',
            'format_id' => 'video_720p',
            'status' => 'processing',
            'progress' => 50,
            'download_token' => Str::random(40),
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $response = $this->getJson("/api/media/jobs/{$download->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'processing',
                    'progress' => 50,
                ],
            ]);
    }

    public function test_get_job_status_returns_404_for_nonexistent_job(): void
    {
        $response = $this->getJson('/api/media/jobs/nonexistent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'JOB_NOT_FOUND',
                ],
            ]);
    }

    public function test_get_job_status_returns_410_for_expired_job(): void
    {
        $download = MediaDownload::create([
            'uuid' => (string) Str::uuid(),
            'platform' => 'youtube',
            'source_url_hash' => hash('sha256', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
            'title' => 'Expired Video',
            'status' => 'completed',
            'progress' => 100,
            'download_token' => Str::random(40),
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->getJson("/api/media/jobs/{$download->uuid}");

        $response->assertStatus(410)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_EXPIRED',
                ],
            ]);
    }

    public function test_download_file_streams_completed_media(): void
    {
        $tempPath = storage_path('app/media_temp/test_file.mp4');
        File::ensureDirectoryExists(dirname($tempPath));
        file_put_contents($tempPath, 'sample binary video data');

        $token = Str::random(40);
        $download = MediaDownload::create([
            'uuid' => (string) Str::uuid(),
            'platform' => 'youtube',
            'source_url_hash' => hash('sha256', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
            'title' => 'Downloadable Video',
            'format_id' => 'video_720p',
            'extension' => 'mp4',
            'status' => 'completed',
            'progress' => 100,
            'file_path' => $tempPath,
            'file_name' => 'test_file.mp4',
            'file_size' => strlen('sample binary video data'),
            'mime_type' => 'video/mp4',
            'download_token' => $token,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $response = $this->get("/api/media/file/{$token}");

        $response->assertStatus(200);
        $this->assertSame('video/mp4', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        // Clean up created file
        @unlink($tempPath);
    }

    public function test_cleanup_command_removes_expired_files_and_records(): void
    {
        $tempPath = storage_path('app/media_temp/expired_test.mp4');
        File::ensureDirectoryExists(dirname($tempPath));
        file_put_contents($tempPath, 'expired content');

        $download = MediaDownload::create([
            'uuid' => (string) Str::uuid(),
            'platform' => 'youtube',
            'source_url_hash' => hash('sha256', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
            'title' => 'Expired Clean Record',
            'status' => 'completed',
            'file_path' => $tempPath,
            'download_token' => Str::random(40),
            'expires_at' => Carbon::now()->subHours(3),
        ]);

        $this->artisan('media:clean')
            ->assertExitCode(0);

        $download->refresh();
        $this->assertSame('failed', $download->status);
        $this->assertNull($download->file_path);
        $this->assertFalse(File::exists($tempPath));
    }
}
