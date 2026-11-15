<?php

namespace Tests\Unit;

use App\Service\Media\MediaKind;
use PHPUnit\Framework\TestCase;

class MediaKindTest extends TestCase
{
    public function test_classifies_case_insensitively(): void
    {
        $this->assertSame(MediaKind::Art, MediaKind::fromPath('A/b/Pic.JPG'));
        $this->assertSame(MediaKind::Art, MediaKind::fromPath('A/b/pic.webp'));
        $this->assertSame(MediaKind::Music, MediaKind::fromPath('x/y.Mp3'));
        $this->assertSame(MediaKind::Music, MediaKind::fromPath('x/y.FLAC'));
    }

    public function test_other_files_are_not_media(): void
    {
        $this->assertNull(MediaKind::fromPath('x/y.pdf'));
        $this->assertNull(MediaKind::fromPath('x/noext'));
        $this->assertNull(MediaKind::fromPath('x/notes.txt'));
    }
}
