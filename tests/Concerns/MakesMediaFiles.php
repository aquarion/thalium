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

    /**
     * A minimal ID3v2.3-tagged "MP3": just the tag followed by silence, which is
     * enough for getID3 to read the tags. $frames maps frame id => text,
     * e.g. ['TIT2' => 'Title', 'TRCK' => '3/12'].
     */
    private function mp3WithTags(array $frames): string
    {
        $body = '';
        foreach ($frames as $id => $text) {
            $data = "\x00".$text; // ISO-8859-1
            $body .= $id.pack('N', strlen($data))."\x00\x00".$data;
        }

        $size = strlen($body);
        $syncsafe = chr(($size >> 21) & 0x7F).chr(($size >> 14) & 0x7F).chr(($size >> 7) & 0x7F).chr($size & 0x7F);

        return "ID3\x03\x00\x00".$syncsafe.$body.str_repeat("\x00", 1024);
    }
}
