<?php

namespace App\Services\Media\Contracts;

interface MediaExtractorInterface
{
    /**
     * Determine if this extractor supports the given URL.
     */
    public function supports(string $url): bool;

    /**
     * Get platform identifier ('youtube', 'instagram', etc.).
     */
    public function getPlatform(): string;

    /**
     * Retrieve media metadata and available download formats.
     *
     * @return array{
     *     platform: string,
     *     title: string,
     *     thumbnail: string|null,
     *     duration: int|null,
     *     author: string|null,
     *     formats: array<int, array{
     *         id: string,
     *         type: string,
     *         extension: string,
     *         quality: string,
     *         filesize: int|null
     *     }>
     * }
     */
    public function getInfo(string $url): array;

    /**
     * Download and process the selected media format.
     */
    public function prepareDownload(string $url, string $formatId, ?callable $progressCallback = null): DownloadResult;
}
