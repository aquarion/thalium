<?php

namespace Tests\Concerns;

trait MakesMediaFiles
{
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    /** One second of 8-bit mono silence at 8kHz. */
    private function wav(): string
    {
        $data = str_repeat("\x80", 8000);

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8)
            .'data'.pack('V', strlen($data)).$data;
    }
}
