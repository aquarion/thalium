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
        $size = @getimagesize(Storage::disk($this->disk)->path($this->filename));

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

        // Huge battle maps can exceed ImageMagick's memory limits if decoded in full
        // ("cache resources exhausted"). For JPEGs, have libjpeg downscale while
        // decoding; the hint is ignored for other formats.
        $image->setOption('jpeg:size', '800x800');
        $image->readImage(Storage::disk($this->disk)->path($this->filename).'[0]');
        $image->thumbnailImage(400, 400, true);
        $image->setImageFormat('png');

        return $image;

    }// end generateDocThumbnail()

}// end class
