<?php

namespace App\Service\Media;

/**
 * Which library a media file lives in: the Systems library (`libris` disk) or
 * the `RPG/Maps` tree beside it (`maps` disk).
 */
enum MediaSource: string
{
    case Library = 'library';
    case Maps = 'maps';

    public function disk(): string
    {
        return match ($this) {
            self::Library => 'libris',
            self::Maps => 'maps',
        };

    }// end disk()

    /**
     * The maps library is optional: without a configured root it is skipped.
     */
    public function available(): bool
    {
        return match ($this) {
            self::Library => true,
            self::Maps => (string) config('filesystems.disks.maps.root') !== '',
        };

    }// end available()

}// end enum
