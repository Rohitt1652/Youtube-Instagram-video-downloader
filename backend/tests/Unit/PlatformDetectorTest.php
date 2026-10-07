<?php

namespace Tests\Unit;

use App\Exceptions\UnsupportedPlatformException;
use App\Services\Media\PlatformDetector;
use App\Services\Media\UrlValidator;
use Tests\TestCase;

class PlatformDetectorTest extends TestCase
{
    protected PlatformDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $validator = new UrlValidator();
        $this->detector = new PlatformDetector($validator);
    }

    public function test_detects_youtube_video(): void
    {
        $res = $this->detector->detect('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertSame('youtube', $res['platform']);
        $this->assertSame('youtube_video', $res['content_type']);
    }

    public function test_detects_youtube_short(): void
    {
        $res = $this->detector->detect('https://www.youtube.com/shorts/dQw4w9WgXcQ');
        $this->assertSame('youtube', $res['platform']);
        $this->assertSame('youtube_short', $res['content_type']);
    }

    public function test_detects_instagram_reel(): void
    {
        $res = $this->detector->detect('https://www.instagram.com/reel/C1234567/');
        $this->assertSame('instagram', $res['platform']);
        $this->assertSame('instagram_reel', $res['content_type']);
    }

    public function test_detects_instagram_post(): void
    {
        $res = $this->detector->detect('https://www.instagram.com/p/C1234567/');
        $this->assertSame('instagram', $res['platform']);
        $this->assertSame('instagram_post', $res['content_type']);
    }

    public function test_throws_for_unsupported_platform(): void
    {
        $this->expectException(UnsupportedPlatformException::class);
        $this->detector->detect('https://twitter.com/i/status/123');
    }
}
