<?php

namespace Tests\Feature;

use App\Service\Media\MediaSource;
use Tests\TestCase;

class MediaSourceTest extends TestCase
{
    public function test_disks(): void
    {
        $this->assertSame('libris', MediaSource::Library->disk());
        $this->assertSame('maps', MediaSource::Maps->disk());
    }

    public function test_maps_source_unavailable_without_root(): void
    {
        config(['filesystems.disks.maps.root' => null]);

        $this->assertFalse(MediaSource::Maps->available());
        $this->assertTrue(MediaSource::Library->available());
    }

    public function test_maps_source_available_with_root(): void
    {
        config(['filesystems.disks.maps.root' => '/mnt/maps']);

        $this->assertTrue(MediaSource::Maps->available());
    }
}
