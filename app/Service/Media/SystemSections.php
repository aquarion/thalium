<?php

namespace App\Service\Media;

use App\Libris\LibrisInterface;
use App\Models\MediaItem;
use Illuminate\Support\Facades\Log;

/**
 * Item counts for the Documents / Art / Music / Maps tabs of a system.
 */
class SystemSections
{
    public function __construct(private LibrisInterface $libris) {}// end __construct()

    /**
     * @return array{documents:int,art:int,music:int,maps:int}
     */
    public function counts(string $system, ?string $tag = null): array
    {
        $media = MediaItem::countsForSystem($system, $tag);

        return [
            'documents' => $this->documentCount($system, $tag),
            'art' => $media['art'],
            'music' => $media['music'],
            'maps' => $media['map'],
        ];

    }// end counts()

    /**
     * Every document in the system, or those with the tag. With no tag the
     * argument is `false` (no filter): the PDF page's own default of `0` means
     * "untagged documents only". The media pages must keep working if the
     * document index is unavailable.
     */
    private function documentCount(string $system, ?string $tag): int
    {
        try {
            return (int) $this->libris->docsBySystem($system, 1, 1, $tag ?? false)['hits']['total']['value'];
        } catch (\Throwable $e) {
            Log::warning("[Media] Could not count documents for $system: ".$e->getMessage());

            return 0;
        }

    }// end documentCount()

}// end class
