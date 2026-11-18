<?php

namespace App\Service\Media;

/**
 * Decides, from a library path alone, whether a file is media and how it files.
 * Pure: no disk access, so it can run anywhere.
 */
class MediaClassifier
{
    private const MAPS_DIRECTORY = 'maps';

    /**
     * Null when the path is not media: hidden, outside a directory, or a type that
     * isn't ingested (including a PDF outside a Maps folder, which is a Document).
     */
    public static function classify(MediaSource $source, string $path): ?MediaClassification
    {
        $segments = explode('/', $path);

        if (count($segments) < 2) {
            return null;
        }

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                return null;
            }
        }

        array_pop($segments);
        $first = array_shift($segments);

        return match ($source) {
            MediaSource::Library => self::classifyLibrary($path, $first, $segments),
            MediaSource::Maps => self::classifyMapsTree($path, $first, $segments),
        };

    }// end classify()

    /**
     * True for a Systems-library file that counts as a map.
     */
    public static function isMapPath(string $path): bool
    {
        return self::classify(MediaSource::Library, $path)?->kind === MediaKind::Map;

    }// end isMapPath()

    /**
     * @param  list<string>  $directories  directories below the system directory
     */
    private static function classifyLibrary(string $path, string $systemDirectory, array $directories): ?MediaClassification
    {
        $system = self::normalise($systemDirectory);
        $withoutMaps = array_values(array_filter(
            $directories,
            fn (string $directory) => strtolower($directory) !== self::MAPS_DIRECTORY
        ));

        if (count($withoutMaps) !== count($directories)) {
            if (! MediaKind::isImage($path) && ! MediaKind::isPdf($path)) {
                return null;
            }

            return new MediaClassification(MediaKind::Map, $system, null, $withoutMaps);
        }

        $kind = MediaKind::fromPath($path);

        return $kind === null ? null : new MediaClassification($kind, $system, null, $directories);

    }// end classifyLibrary()

    /**
     * @param  list<string>  $directories  directories below the collection directory
     */
    private static function classifyMapsTree(string $path, string $collectionDirectory, array $directories): ?MediaClassification
    {
        if (! MediaKind::isImage($path) && ! MediaKind::isPdf($path)) {
            return null;
        }

        return new MediaClassification(MediaKind::Map, null, self::normalise($collectionDirectory), $directories);

    }// end classifyMapsTree()

    /**
     * The same system-name rule as ParserService: strip an extension, `-`/`_` become spaces.
     */
    private static function normalise(string $directory): string
    {
        $name = preg_replace('!\.[^.]+$!', '', $directory);

        return preg_replace('!-|_!', ' ', $name);

    }// end normalise()

}// end class
