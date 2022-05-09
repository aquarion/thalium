<?php
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

        list($lines, $lineHeight) = wordWrapAnnotation($im, $draw, $text, 200);
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

        return $canvas;
    }//end generateThumbnail()
