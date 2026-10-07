<?php

namespace Tests\Feature;

use App\Services\Media\Contracts\DownloadResult;
use App\Services\Media\Contracts\MediaExtractorInterface;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear rate limiter cache
        RateLimiter::clear(sha1('media-info|127.0.0.1'));
        RateLimiter::clear(sha1('media-download|127.0.0.1'));

        // Register fake extractor so info requests succeed quickly
        $fakeExtractor = new class implements MediaExtractorInterface {
            public function supports(string $url): bool { return true; }
            public function getPlatform(): string { return 'youtube'; }
            public function getInfo(string $url): array {
                return [
                    'platform' => 'youtube',
                    'title' => 'Rate Limit Test Video',
                    'thumbnail' => null,
                    'duration' => 60,
                    'author' => 'Author',
                    'formats' => [
                        ['id' => 'video_720p', 'type' => 'video', 'extension' => 'mp4', 'quality' => '720p', 'filesize' => null],
                    ],
                ];
            }
            public function prepareDownload(string $url, string $formatId, ?callable $progressCallback = null): DownloadResult {
                $temp = storage_path('app/media_temp/dummy.mp4');
                File::ensureDirectoryExists(dirname($temp));
                file_put_contents($temp, 'dummy');
                return new DownloadResult($temp, 'dummy.mp4', 5, 'video/mp4', 'mp4', 'video', $formatId);
            }
        };

        $mediaService = $this->app->make(MediaService::class);
        $mediaService->registerExtractor($fakeExtractor);
        $this->app->instance(MediaService::class, $mediaService);
    }

    public function test_media_info_rate_limiter_throttles_excessive_requests(): void
    {
        // Set low limit for fast testing
        config(['media.rate_limits.info_per_minute' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/media/info', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);
            $response->assertStatus(200);
        }

        // The 4th request must return 429 Too Many Requests
        $response = $this->postJson('/api/media/info', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                ],
            ]);
    }

    public function test_media_download_rate_limiter_throttles_excessive_requests(): void
    {
        config(['media.rate_limits.download_per_minute' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $response = $this->postJson('/api/media/download', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'format_id' => 'video_720p',
            ]);
            $response->assertStatus(202);
        }

        // The 3rd request must return 429
        $response = $this->postJson('/api/media/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'format_id' => 'video_720p',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                ],
            ]);
    }
}
