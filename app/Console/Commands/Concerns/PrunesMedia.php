<?php

namespace App\Console\Commands\Concerns;

use App\Service\Media\MediaIngestService;
use App\Service\Media\MediaSource;

trait PrunesMedia
{
    /**
     * The sources to update: the Systems library, and the maps tree when it is configured.
     *
     * @return list<MediaSource>
     */
    protected function sources(): array
    {
        return array_values(array_filter(MediaSource::cases(), fn (MediaSource $source) => $source->available()));

    }// end sources()

    /**
     * Remove media rows for deleted files. Fails when the Systems library looks unmounted
     * or emptied, so the scheduler flags it rather than the gallery being silently wiped.
     * The maps tree is optional: an empty one is skipped with a notice, not a failure.
     */
    protected function pruneMedia(MediaIngestService $media): int
    {
        $status = self::SUCCESS;

        foreach ($this->sources() as $source) {
            $removed = $media->prune($source);

            if ($removed !== null) {
                $this->line("Removed $removed deleted {$source->value} files");
            } elseif ($source === MediaSource::Library) {
                $this->error('Skipped pruning: the library looks empty or unavailable');
                $status = self::FAILURE;
            } else {
                $this->warn('Skipped pruning the maps library: it looks empty or unavailable');
            }
        }

        return $status;

    }// end pruneMedia()

}// end trait
