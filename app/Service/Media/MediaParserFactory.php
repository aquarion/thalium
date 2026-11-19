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
     * The parser for an image, audio or map-PDF path, or null for anything else. The
     * parser constructors may throw LibrisNotFound / LibrisTooLarge.
     */
    public static function make(string $file, MediaSource $source = MediaSource::Library): ?ParserService
    {
        if (MediaKind::isPdf($file)) {
            return new ParsePdfMapService($file, $source->disk());
        }

        return match (MediaKind::fromPath($file)) {
            MediaKind::Art => new ParseImageService($file, $source->disk()),
            MediaKind::Music => new ParseAudioService($file, $source->disk()),
            default => null,
        };

    }// end make()

}// end class
