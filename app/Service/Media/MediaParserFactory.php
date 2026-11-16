<?php

namespace App\Service\Media;

use App\Service\ParserService;

/**
 * Deliberately separate from LibrisService::getParser(), which decides what the
 * PDF indexer sends to Elasticsearch; media must never go through that.
 */
class MediaParserFactory
{
    /**
     * The parser for an image or audio path, or null for anything else. The
     * parser constructors may throw LibrisNotFound / LibrisTooLarge.
     */
    public static function make(string $file): ?ParserService
    {
        return match (MediaKind::fromPath($file)) {
            MediaKind::Art => new ParseImageService($file),
            MediaKind::Music => new ParseAudioService($file),
            null => null,
        };

    }// end make()

}// end class
