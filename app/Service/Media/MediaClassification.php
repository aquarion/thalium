<?php

namespace App\Service\Media;

/**
 * What a library path is: its kind, and where it files under.
 */
final class MediaClassification
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly MediaKind $kind,
        public readonly ?string $system,
        public readonly ?string $collection,
        public readonly array $tags,
    ) {}// end __construct()

}// end class
