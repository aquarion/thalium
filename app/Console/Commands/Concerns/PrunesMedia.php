<?php

namespace App\Console\Commands\Concerns;

use App\Service\Media\MediaIngestService;

trait PrunesMedia
{
    /**
     * Remove media rows for deleted files. Fails when the library looks unmounted or
     * emptied, so the scheduler flags it rather than the gallery being silently wiped.
     */
    protected function pruneMedia(MediaIngestService $media): int
    {
        $removed = $media->prune();

        if ($removed === null) {
            $this->error('Skipped pruning: the library looks empty or unavailable');

            return self::FAILURE;
        }

        $this->line("Removed $removed deleted files");

        return self::SUCCESS;

    }// end pruneMedia()

}// end trait
