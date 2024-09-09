<?php

use Imagick as Imagek;
use ImagickDraw as ImagekDraw;

function wordWrapAnnotation($image, $draw, $text, $maxWidth)
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


function genericThumbnail($text)
{
    $height = 265;
    $width  = 200;

    // Create a new canvas object and a white image
    $canvas = new \Imagick();
    $canvas->setGravity(Imagick::GRAVITY_CENTER);
    $canvas->newImage($width, $height, "#380744");

    // // Create imagickdraw object
    $draw = new \ImagickDraw();

    // // Start a new pattern called "gradient"
    // $draw->pushPattern('gradient', 0, 0, 50, 50);

    // // Composite the gradient on the pattern
    // $draw->composite(Imagick::COMPOSITE_OVER, 0, 0, 50, 50, $im);

    // // Close the pattern
    // $draw->popPattern();

    // // Use the pattern called "gradient" as the fill
    // $draw->setFillPatternURL('#gradient');

    $draw->setGravity(Imagick::GRAVITY_CENTER);
    $fontSize = 42;

    // // Set font size to 52
    $draw->setFontSize(32);
    $draw->setFillColor("white");
    $draw->setFontSize($fontSize);
    $draw->setFontWeight(800);
    $draw->setFont(resource_path("genericThumbnail/Macondo-Regular.ttf"));

    list($lines, $lineHeight) = wordWrapAnnotation($canvas, $draw, $text, 200);
    for ($i = 0; $i < count($lines); $i++) {
        $y = ($i - (count($lines) / 2) + .5);
        // $image->annotateImage($draw, $xpos, $ypos + $i*$lineHeight, 0, $lines[$i]);
        $size      = $fontSize;
        $textwidth = $canvas->queryFontMetrics($draw, $lines[$i])['textWidth'];
        while ($textwidth > ($width - 10) && $size > 2) {
            $size--;
            $draw->setFontSize($size);
            $textwidth = $canvas->queryFontMetrics($draw, $lines[$i])['textWidth'];
        }

        $draw->annotation(0, (0 + $y * $lineHeight), $lines[$i]);
    }

    // Let's read the images.
    $icon = new Imagick();
    if ($icon->readImage(resource_path('genericThumbnail/noun-book-of-spells.png')) === false) {
        throw new Exception();
    }

    $iconBorder = 20;
    $icon->trimImage(1);
    $icon->adaptiveResizeImage(($width - $iconBorder), $height, true);
    $iconY = (($height - $icon->getImageHeight()) / 2);
    $iconX = (($iconBorder) / 2 - 2);

    // Annotate some text

    $canvas->compositeImage($icon, Imagick::COMPOSITE_DEFAULT, $iconX, $iconY);

    // Draw the ImagickDraw on to the canvas
    $canvas->drawImage($draw);

    // 1px black border around the image
    $canvas->borderImage('black', 1, 1);

    // Set the format to PNG
    $canvas->setImageFormat('png');

    return $canvas;

}//end genericThumbnail()
