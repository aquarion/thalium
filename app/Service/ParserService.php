<?php

namespace App\Service;

use App\Exceptions;

use Elasticsearch;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

abstract class ParserService
{
    protected $file;

    protected $elasticSearchIndex;

    public $lastModified;

    public $system;

    public $tags;

    public $title;


    abstract public function parsePages();

    public function generateTags()
    {
        $tags = explode('/', $this->filename);
        array_pop($tags); // remove the filename from the tags list
        return $tags;
    }


    public function __construct($file, $elasticSearchIndex)
    {
        if (Storage::disk('libris')->missing($file)) {
            Log::error("[AddDoc] ".$this->filename." in 'total existance failure' error");
            throw new Exceptions\LibrisNotFound();
        }

        $this->filename           = $file;
        $this->elasticSearchIndex = $elasticSearchIndex;
        $this->lastModified       = Storage::disk('libris')->lastModified($file);

        $this->tags = $this->generateTags();

        $system = array_shift($this->tags);
        $system = preg_replace('!\.[^.]+$!', '', $system);
        $system = preg_replace('!-|_!', ' ', $system);

        $this->system = $system;

        $boom = explode("/", $this->filename);

        $title       = array_pop($boom);
        $title       = preg_replace('!\.[^.]+$$!', '', $title);
        $title       = preg_replace('!-|_!', ' ', $title);
        $this->title = $title;

        $size   = Storage::disk('libris')->size($this->filename);
        $sizeMB = number_format($size / 1024);

        if ($size > (1024 * 1024 * 512)) {
            Log::error("[AddDoc] {$this->title} is ".$sizeMB."Mb, too large to index");
            throw new Exceptions\LibrisTooLarge();
        } else {
            Log::info("[AddDoc] {$this->title} is a ".$sizeMB."Mb");
        }
    }//end __construct()



    public function wordWrapAnnotation($image, $draw, $text, $maxWidth)
    {
        $text = trim($text);

        $words      = preg_split('%\s%', $text, -1, PREG_SPLIT_NO_EMPTY);
        $lines      = [];
        $i          = 0;
        $lineHeight = 0;

        while (count($words) > 0) {
            $metrics    = $image->queryFontMetrics($draw, implode(' ', array_slice($words, 0, ++$i)));
            $lineHeight = max($metrics['textHeight'], $lineHeight);

            // check if we have found the word that exceeds the line width
            if ($metrics['textWidth'] > $maxWidth or count($words) < $i) {
                // handle case where a single word is longer than the allowed line width (just add this as a word on its own line?)
                if ($i == 1) {
                    $i++;
                }

                $lines[] = implode(' ', array_slice($words, 0, --$i));
                $words   = array_slice($words, $i);
                $i       = 0;
            }
        }

        return [
            $lines,
            $lineHeight,
        ];
    }//end wordWrapAnnotation()


    public function generateThumbnail()
    {
        Log::info("[AddDoc] {$this->filename} Generating Thumbnail");

        // Create a new imagick object
        $im = new \Imagick();

        // Create new image. This will be used as fill pattern
        $im->newPseudoImage(50, 50, "gradient:red-black");

        // Create imagickdraw object
        $draw = new \ImagickDraw();

        // Start a new pattern called "gradient"
        $draw->pushPattern('gradient', 0, 0, 50, 50);

        // Composite the gradient on the pattern
        $draw->composite(\Imagick::COMPOSITE_OVER, 0, 0, 50, 50, $im);

        // Close the pattern
        $draw->popPattern();

        // Use the pattern called "gradient" as the fill
        $draw->setFillPatternURL('#gradient');

        $draw->setGravity(\Imagick::GRAVITY_CENTER);

        // Set font size to 52
        $draw->setFontSize(32);

        list($lines, $lineHeight) = $this->wordWrapAnnotation($im, $draw, $this->title, 200);
        for ($i = 0; $i < count($lines); $i++) {
            // $image->annotateImage($draw, $xpos, $ypos + $i*$lineHeight, 0, $lines[$i]);
            $draw->annotation(0, (0 + $i * $lineHeight), $lines[$i]);
        }

        // Annotate some text
        // Create a new canvas object and a white image
        $canvas = new \Imagick();
        $canvas->newImage(200, 300, "white");

        // Draw the ImagickDraw on to the canvas
        $canvas->drawImage($draw);

        // 1px black border around the image
        $canvas->borderImage('black', 1, 1);

        // Set the format to PNG
        $canvas->setImageFormat('png');

        $imageData = base64_encode($canvas);
        return 'data:image/png;base64,'.$imageData;
    }//end generateThumbnail()
}//end class
