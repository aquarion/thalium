<?php

namespace App\Service\Media;

use App\Libris\LibrisInterface;
use App\Models\MediaItem;
use Illuminate\Support\Facades\Log;

/**
 * Item counts for the Documents / Art / Music tabs of a system.
 */
class SystemSections
{
    public function __construct(private LibrisInterface $libris) {}// end __construct()

    /**
     * @param  int|null  $documents  The PDF count if the caller already has it; otherwise it is looked up.
     * @return array{documents:int,art:int,music:int}
     */
    public function counts(string $system, ?int $documents = null): array
    {
        $media = MediaItem::countsForSystem($system);

        return [
            'documents' => $documents ?? $this->documentCount($system),
            'art' => $media['art'],
            'music' => $media['music'],
        ];

    }// end counts()

    /**
     * The media pages must keep working if the document index is unavailable.
     */
    private function documentCount(string $system): int
    {
        try {
            return (int) $this->libris->docsBySystem($system, 1, 1, 0)['hits']['total']['value'];
        } catch (\Throwable $e) {
            Log::warning("[Media] Could not count documents for $system: ".$e->getMessage());

            return 0;
        }

    }// end documentCount()

}// end class
