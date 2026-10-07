<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_downloads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('platform', 32)->index();
            $table->string('source_url_hash', 64)->index();
            $table->string('title', 500);
            $table->string('format_id', 100)->nullable();
            $table->string('format', 20)->default('video'); // video or audio
            $table->string('quality', 50)->nullable();
            $table->string('extension', 10)->default('mp4');
            $table->string('status', 30)->default('queued')->index(); // queued, processing, completed, failed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('file_path', 1000)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->string('download_token', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_downloads');
    }
};
