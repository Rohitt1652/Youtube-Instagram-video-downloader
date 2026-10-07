<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MediaDownload extends Model
{
    use HasFactory;

    protected $table = 'media_downloads';

    protected $fillable = [
        'uuid',
        'platform',
        'source_url_hash',
        'title',
        'format_id',
        'format',
        'quality',
        'extension',
        'status',
        'progress',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'error_message',
        'download_token',
        'expires_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'file_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->download_token)) {
                $model->download_token = Str::random(40);
            }
        });
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markProcessing(int $progress = 10): void
    {
        $this->update([
            'status' => 'processing',
            'progress' => max($this->progress, $progress),
        ]);
    }

    public function markProgress(int $progress): void
    {
        $this->update([
            'progress' => min(100, max(0, $progress)),
        ]);
    }

    public function markCompleted(string $filePath, string $fileName, int $fileSize, string $mimeType, Carbon $expiresAt): void
    {
        $this->update([
            'status' => 'completed',
            'progress' => 100,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'expires_at' => $expiresAt,
            'error_message' => null,
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function cleanupFile(): bool
    {
        if ($this->file_path && File::exists($this->file_path)) {
            return File::delete($this->file_path);
        }
        return false;
    }
}
