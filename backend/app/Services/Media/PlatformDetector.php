<?php

namespace App\Services\Media;

use App\Exceptions\UnsupportedPlatformException;

class PlatformDetector
{
    public function __construct(
        protected UrlValidator $urlValidator
    ) {}

    /**
     * Detect platform from normalized URL.
     *
     * @return array{
     *     platform: string,
     *     normalized_url: string,
     *     content_type: string
     * }
     */
    public function detect(string $rawUrl): array
    {
        $normalizedUrl = $this->urlValidator->validateAndNormalize($rawUrl);
        $host = parse_url($normalizedUrl, PHP_URL_HOST) ?? '';
        $platform = $this->urlValidator->matchPlatform($host);

        if (!$platform) {
            throw new UnsupportedPlatformException("Unsupported platform for host: {$host}");
        }

        $path = parse_url($normalizedUrl, PHP_URL_PATH) ?? '';
        $contentType = $this->detectContentType($platform, $path);

        return [
            'platform' => $platform,
            'normalized_url' => $normalizedUrl,
            'content_type' => $contentType,
        ];
    }

    protected function detectContentType(string $platform, string $path): string
    {
        if ($platform === 'youtube') {
            if (str_contains($path, '/shorts/')) {
                return 'youtube_short';
            }
            return 'youtube_video';
        }

        if ($platform === 'instagram') {
            if (str_contains($path, '/reel/')) {
                return 'instagram_reel';
            }
            if (str_contains($path, '/p/')) {
                return 'instagram_post';
            }
            return 'instagram_video';
        }

        return 'unknown';
    }
}
