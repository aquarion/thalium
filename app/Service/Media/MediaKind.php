<?php

namespace App\Service\Media;

enum MediaKind: string
{
    case Art = 'art';
    case Music = 'music';

    private const ART_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const MUSIC_EXTENSIONS = ['mp3', 'flac', 'ogg', 'm4a', 'wav'];

    /**
     * Classify a library path by its (case-insensitive) extension.
     */
    public static function fromPath(string $path): ?self
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::ART_EXTENSIONS, true)) {
            return self::Art;
        }

        if (in_array($extension, self::MUSIC_EXTENSIONS, true)) {
            return self::Music;
        }

        return null;

    }// end fromPath()

}// end enum
