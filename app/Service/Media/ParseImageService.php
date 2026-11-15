<?php

namespace App\Service\Media;

use App\Service\ParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ParseImageService extends ParserService implements MediaParserInterface
{
    public function kind(): MediaKind
    {
        return MediaKind::Art;

    }// end kind()

    public function parsePages()
    {
        return [];

    }// end parsePages()

    public function metadata(): array
    {
        $size = @getimagesize(Storage::disk('libris')->path($this->filename));

        if (! $size) {
            Log::warning("[Parser] Could not read image dimensions for {$this->filename}");

            return [];
        }

        return ['width' => $size[0], 'height' => $size[1]];

    }// end metadata()

    /**
     * A PNG thumbnail whose longest side is at most 400px (first frame for animations).
     */
    public function generateDocThumbnail()
    {
        $image = new \Imagick;
        $image->readImage(Storage::disk('libris')->path($this->filename).'[0]');
        $image->thumbnailImage(400, 400, true);
        $image->setImageFormat('png');

        return $image;

    }// end generateDocThumbnail()

}// end class
