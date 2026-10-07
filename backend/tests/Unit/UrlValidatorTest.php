<?php

namespace Tests\Unit;

use App\Exceptions\InvalidUrlException;
use App\Exceptions\SsrfDetectedException;
use App\Exceptions\UnsupportedPlatformException;
use App\Services\Media\UrlValidator;
use Tests\TestCase;

class UrlValidatorTest extends TestCase
{
    protected UrlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new UrlValidator();
    }

    public function test_accepts_valid_https_youtube_url(): void
    {
        $normalized = $this->validator->validateAndNormalize('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $normalized);
    }

    public function test_accepts_valid_https_youtu_be_url(): void
    {
        $normalized = $this->validator->validateAndNormalize('https://youtu.be/dQw4w9WgXcQ');
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $normalized);
    }

    public function test_accepts_valid_https_instagram_reel_url(): void
    {
        $normalized = $this->validator->validateAndNormalize('https://www.instagram.com/reel/Cx12345/');
        $this->assertSame('https://www.instagram.com/reel/Cx12345/', $normalized);
    }

    public function test_rejects_insecure_http_url(): void
    {
        $this->expectException(InvalidUrlException::class);
        $this->validator->validateAndNormalize('http://www.youtube.com/watch?v=dQw4w9WgXcQ');
    }

    public function test_rejects_unsupported_domain(): void
    {
        $this->expectException(UnsupportedPlatformException::class);
        $this->validator->validateAndNormalize('https://vimeo.com/12345678');
    }

    public function test_rejects_command_injection_characters(): void
    {
        $this->expectException(InvalidUrlException::class);
        $this->validator->validateAndNormalize('https://www.youtube.com/watch?v=123;rm -rf /');
    }

    public function test_rejects_backticks_and_pipes(): void
    {
        $this->expectException(InvalidUrlException::class);
        $this->validator->validateAndNormalize('https://www.youtube.com/watch?v=`id`|calc.exe');
    }

    public function test_rejects_direct_ip_addresses_ssrf(): void
    {
        $this->expectException(SsrfDetectedException::class);
        $this->validator->validateAndNormalize('https://127.0.0.1/video');
    }

    public function test_rejects_cloud_metadata_ip_ssrf(): void
    {
        $this->expectException(SsrfDetectedException::class);
        $this->validator->validateAndNormalize('https://169.254.169.254/latest/meta-data/');
    }

    public function test_correctly_hashes_normalized_url(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $hash = $this->validator->hashUrl($url);

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', $url), $hash);
    }
}
