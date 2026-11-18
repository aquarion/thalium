<?php

namespace App\Service\Media;

enum MediaKind: string
{
    case Art = 'art';
    case Music = 'music';
    case Map = 'map';

    private const ART_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const MUSIC_EXTENSIONS = ['mp3', 'flac', 'ogg', 'm4a', 'wav'];

    private const PDF_EXTENSION = 'pdf';

    public static function isImage(string $path): bool
    {
        return in_array(self::extension($path), self::ART_EXTENSIONS, true);

    }// end isImage()

    public static function isPdf(string $path): bool
    {
        return self::extension($path) === self::PDF_EXTENSION;

    }// end isPdf()

    private static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));

    }// end extension()

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
