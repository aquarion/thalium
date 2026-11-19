<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrunesMedia;
use App\Jobs\ScanFile;
use App\Libris\LibrisInterface;
use App\Models\MediaItem;
use App\Service\Media\MediaIngestService;
use App\Service\Media\MediaKind;
use App\Service\Media\MediaSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LibrisUpdateMedia extends Command
{
    use PrunesMedia;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:update_media {--foreground : Run in foreground } {--force : Re-read every file even if unchanged (runs in the foreground)} {--start=. : The directory to start at}';

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
        $foreground = $this->option('foreground') || $this->option('force');

        foreach ($this->sources() as $source) {
            // --start narrows the Systems library only; the maps tree is always walked whole.
            $root = $source === MediaSource::Library ? $this->option('start') : '.';

            $this->line('Updating '.$source->value.' media from '.Storage::disk($source->disk())->path($root));

            if ($foreground) {
                $this->foregroundUpdate($media, $root, (bool) $this->option('force'), $source);
            } else {
                $this->backgroundUpdate($media, $root, $source);
            }
        }

        return $this->pruneMedia($media);

    }// end handle()

    private function backgroundUpdate(MediaIngestService $media, string $root, MediaSource $source): void
    {
        $dispatched = 0;

        foreach ($media->changedPaths($root, $source) as $path) {
            ScanFile::dispatch($path, $source->value);
            $dispatched++;
        }

        $this->line("Dispatched $dispatched new or changed media files");

    }// end backgroundUpdate()

    private function foregroundUpdate(MediaIngestService $media, string $root, bool $force, MediaSource $source): void
    {
        $added = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($media->paths($root, $source) as $path) {
            // A map PDF also has to leave the Elasticsearch index, which scanFile() handles.
            if ($source === MediaSource::Library && MediaKind::isPdf($path)) {
                app(LibrisInterface::class)->scanFile($path);
                $item = MediaItem::query()->where('path_hash', MediaItem::hashPath($path))->first();
            } else {
                $item = $media->ingest($path, $force, $source);
            }

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
