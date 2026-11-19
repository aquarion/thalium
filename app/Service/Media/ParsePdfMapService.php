<?php

namespace App\Service\Media;

use App\Service\PDFBoxService;

/**
 * A PDF that is a map: shown in the Maps gallery with a first-page thumbnail,
 * never indexed in Elasticsearch.
 */
class ParsePdfMapService extends PDFBoxService implements MediaParserInterface
{
    public function kind(): MediaKind
    {
        return MediaKind::Map;

    }// end kind()

    public function metadata(): array
    {
        return [];

    }// end metadata()

}// end class
