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

    public function test_map_kind_value(): void
    {
        $this->assertSame('map', MediaKind::Map->value);
    }

    public function test_from_path_never_returns_map(): void
    {
        $this->assertNull(MediaKind::fromPath('a.pdf'));
        $this->assertSame(MediaKind::Art, MediaKind::fromPath('a.jpg'));
    }

    public function test_is_pdf_and_is_image_are_case_insensitive(): void
    {
        $this->assertTrue(MediaKind::isPdf('S/A.PDF'));
        $this->assertFalse(MediaKind::isPdf('S/a.jpg'));
        $this->assertTrue(MediaKind::isImage('S/a.JPEG'));
        $this->assertFalse(MediaKind::isImage('S/a.pdf'));
    }
}
