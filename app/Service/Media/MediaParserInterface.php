<?php

namespace App\Service\Media;

interface MediaParserInterface
{
    public function kind(): MediaKind;

    /**
     * Whatever metadata could be read from the file. Keys are only present when
     * known: art gives width/height; music gives title, artist, album and
     * duration_seconds. Unreadable files give [].
     */
    public function metadata(): array;

}// end interface
