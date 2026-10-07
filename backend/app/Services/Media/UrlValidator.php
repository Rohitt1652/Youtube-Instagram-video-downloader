<?php

namespace App\Services\Media;

use App\Exceptions\InvalidUrlException;
use App\Exceptions\SsrfDetectedException;
use App\Exceptions\UnsupportedPlatformException;

class UrlValidator
{
    /**
     * @var array<string, array<string>>
     */
    protected array $allowedDomains;

    /**
     * Cloud metadata and dangerous IP ranges
     */
    protected const BLOCKED_IPS = [
        '169.254.169.254', // AWS / GCP / Azure metadata
        '169.254.169.253',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
    ];

    public function __construct(?array $allowedDomains = null)
    {
        $default = [
            'youtube' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
            'instagram' => ['instagram.com', 'www.instagram.com', 'm.instagram.com'],
        ];

        if ($allowedDomains !== null) {
            $this->allowedDomains = $allowedDomains;
        } else {
            $this->allowedDomains = (function_exists('config') && app()->has('config'))
                ? config('media.allowed_domains', $default)
                : $default;
        }
    }

    /**
     * Validate, sanitize and normalize user-provided URL.
     *
     * @throws InvalidUrlException
     * @throws UnsupportedPlatformException
     * @throws SsrfDetectedException
     */
    public function validateAndNormalize(string $rawUrl): string
    {
        $trimmed = trim($rawUrl);

        if (empty($trimmed)) {
            throw new InvalidUrlException('URL cannot be empty.');
        }

        // Check for control characters, null bytes, newlines, or command injection characters
        if (preg_match('/[\x00-\x1F\x7F`$|;&<>]/', $trimmed)) {
            throw new InvalidUrlException('URL contains illegal or control characters.');
        }

        // Validate basic URL syntax with filter_var
        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException('Invalid URL format.');
        }

        $parsed = parse_url($trimmed);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new InvalidUrlException('Invalid or incomplete URL.');
        }

        // Rule: Only HTTPS is allowed
        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'https') {
            throw new InvalidUrlException('Only secure HTTPS URLs are supported.');
        }

        // Disallow username / password in URL
        if (!empty($parsed['user']) || !empty($parsed['pass'])) {
            throw new InvalidUrlException('URLs with user credentials are not allowed.');
        }

        $host = strtolower($parsed['host']);

        // Prevent direct IP addresses in host (e.g. https://127.0.0.1 or https://[::1])
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            throw new SsrfDetectedException('Direct IP addresses are not permitted.');
        }

        // Check against strictly allowed domains
        $platform = $this->matchPlatform($host);
        if ($platform === null) {
            throw new UnsupportedPlatformException("Domain '{$host}' is not supported. Supported platforms: YouTube and Instagram.");
        }

        // Prevent SSRF via DNS resolution checks
        $this->verifyDnsAgainstSsrf($host);

        // Sanitize path and query
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        // Reconstruct clean canonical URL (excluding user, pass, fragment)
        return "https://{$host}{$path}{$query}";
    }

    /**
     * Match host to allowed platform.
     */
    public function matchPlatform(string $host): ?string
    {
        $normalizedHost = strtolower(trim($host));

        foreach ($this->allowedDomains as $platform => $domains) {
            foreach ($domains as $domain) {
                if ($normalizedHost === strtolower($domain)) {
                    return $platform;
                }
            }
        }

        return null;
    }

    /**
     * Resolve host and ensure no IP resolves to private, loopback, or link-local ranges.
     *
     * @throws SsrfDetectedException
     */
    public function verifyDnsAgainstSsrf(string $host): void
    {
        // Check for cloud metadata hostnames
        if (str_contains($host, 'metadata') || str_contains($host, 'localhost') || str_contains($host, 'internal')) {
            throw new SsrfDetectedException('Prohibited hostname.');
        }

        $ips = @gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            // DNS resolution failure
            return;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateOrRestrictedIp($ip)) {
                throw new SsrfDetectedException("Resolved IP {$ip} for host {$host} is prohibited (SSRF protection).");
            }
        }
    }

    /**
     * Determine if an IP address belongs to private/reserved/loopback/cloud-metadata networks.
     */
    public function isPrivateOrRestrictedIp(string $ip): bool
    {
        if (in_array($ip, self::BLOCKED_IPS, true)) {
            return true;
        }

        // Check FILTER_FLAG_NO_PRIV_RANGE and FILTER_FLAG_NO_RES_RANGE
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isPublic === false) {
            return true;
        }

        // Carrier-grade NAT (100.64.0.0/10)
        $long = ip2long($ip);
        if ($long !== false) {
            $cgnatStart = ip2long('100.64.0.0');
            $cgnatEnd = ip2long('100.127.255.255');
            if ($long >= $cgnatStart && $long <= $cgnatEnd) {
                return true;
            }

            // Link-local (169.254.0.0/16)
            $linkLocalStart = ip2long('169.254.0.0');
            $linkLocalEnd = ip2long('169.254.255.255');
            if ($long >= $linkLocalStart && $long <= $linkLocalEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate secure SHA-256 hash for URL storage.
     */
    public function hashUrl(string $normalizedUrl): string
    {
        return hash('sha256', $normalizedUrl);
    }
}
