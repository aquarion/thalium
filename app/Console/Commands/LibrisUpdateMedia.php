<?php

namespace App\Console\Commands;

use App\Jobs\ScanFile;
use App\Service\Media\MediaIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LibrisUpdateMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:update_media {--foreground : Run in foreground } {--start=. : The directory to start at}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the art and music gallery from the library, and prune deleted files';

    /**
     * Execute the console command.
     */
    public function handle(MediaIngestService $media): int
    {
        $root = $this->option('start');

        $this->line('Updating media from '.Storage::disk('libris')->path($root));

        if ($this->option('foreground')) {
            $this->foregroundUpdate($media, $root);
        } else {
            $this->backgroundUpdate($media, $root);
        }

        $this->line('Removed '.$media->prune().' deleted files');

        return self::SUCCESS;

    }// end handle()

    private function backgroundUpdate(MediaIngestService $media, string $root): void
    {
        $dispatched = 0;

        foreach ($media->paths($root) as $path) {
            ScanFile::dispatch($path);
            $dispatched++;
        }

        $this->line("Dispatched $dispatched media files");

    }// end backgroundUpdate()

    private function foregroundUpdate(MediaIngestService $media, string $root): void
    {
        $added = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($media->paths($root) as $path) {
            $item = $media->ingest($path);

            if ($item === null) {
                continue;
            }

            if ($item->wasRecentlyCreated) {
                $added++;
            } elseif ($item->wasChanged()) {
                $updated++;
            } else {
                $unchanged++;
            }
        }

        $this->line("Added $added, updated $updated, unchanged $unchanged");

    }// end foregroundUpdate()

}// end class
