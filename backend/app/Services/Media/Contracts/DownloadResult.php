<?php

namespace App\Services\Media\Contracts;

class DownloadResult
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $fileName,
        public readonly int $fileSize,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly ?string $format = null,
        public readonly ?string $quality = null
    ) {}

    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'file_name' => $this->fileName,
            'file_size' => $this->fileSize,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'format' => $this->format,
            'quality' => $this->quality,
        ];
    }
}
