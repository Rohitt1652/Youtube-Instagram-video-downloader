<?php

namespace App\Console\Commands;

use App\Services\Media\MediaService;
use Illuminate\Console\Command;

class CleanExpiredMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired temporary media files and incomplete jobs from disk and database';

    /**
     * Execute the console command.
     */
    public function handle(MediaService $mediaService): int
    {
        $this->info('Starting media cleanup routine...');

        $result = $mediaService->cleanupExpiredFiles();

        $this->info("Cleanup completed successfully.");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Temporary files removed', $result['deleted_files']],
                ['Database records updated', $result['cleaned_records']],
            ]
        );

        return Command::SUCCESS;
    }
}
