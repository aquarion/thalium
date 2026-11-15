<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('libris');
        Storage::fake('thumbnails');
    }

    private function make(string $path, MediaKind $kind, string $system, array $tags = []): MediaItem
    {
        return MediaItem::create([
            'path' => $path,
            'kind' => $kind,
            'system' => $system,
            'tags' => $tags,
            'title' => basename($path),
            'mtime' => 1,
            'size' => 1,
        ]);
    }

    public function test_url_encodes_each_segment(): void
    {
        $item = $this->make('Pathfinder 2e/Maps/a #1?.png', MediaKind::Art, 'Pathfinder 2e', ['Maps']);

        $this->assertStringEndsWith('Pathfinder%202e/Maps/a%20%231%3F.png', $item->url());
    }

    public function test_counts_and_tag_counts(): void
    {
        $this->make('S/Maps/a.png', MediaKind::Art, 'S', ['Maps']);
        $this->make('S/Maps/City/b.png', MediaKind::Art, 'S', ['Maps', 'City']);
        $this->make('S/Ambience/c.mp3', MediaKind::Music, 'S', ['Ambience']);
        $this->make('Other/d.png', MediaKind::Art, 'Other');

        $this->assertSame(['art' => 2, 'music' => 1], MediaItem::countsForSystem('S'));
        $this->assertSame(['art' => 0, 'music' => 0], MediaItem::countsForSystem('Nope'));
        $this->assertSame(['City' => 1, 'Maps' => 2], MediaItem::tagCounts(MediaKind::Art, 'S'));
    }

    public function test_systems_with_media(): void
    {
        $this->make('S/a.png', MediaKind::Art, 'S');
        $this->make('S/b.mp3', MediaKind::Music, 'S');
        $this->make('Other/d.png', MediaKind::Art, 'Other');

        $systems = MediaItem::systemsWithMedia();

        $this->assertSame(['art' => 1, 'music' => 1], $systems['S']);
        $this->assertSame(['art' => 1, 'music' => 0], $systems['Other']);
    }

    public function test_duration_label(): void
    {
        $item = $this->make('S/a.mp3', MediaKind::Music, 'S');
        $this->assertNull($item->durationLabel());

        $item->duration_seconds = 75;
        $this->assertSame('1:15', $item->durationLabel());
    }

    public function test_path_is_keyed_by_hash_not_a_long_unique_string_index(): void
    {
        // A unique index on VARCHAR(1024) fails on MySQL/utf8mb4 (4096 bytes > the 3072 key limit).
        $item = $this->make('S/Maps/a.png', MediaKind::Art, 'S', ['Maps']);

        $this->assertSame(sha1('S/Maps/a.png'), $item->path_hash);

        $unique = collect(Schema::getIndexes('media_items'))->where('unique', true)->pluck('columns');
        $this->assertTrue($unique->contains(['path_hash']));
        $this->assertFalse($unique->contains(['path']));
    }

    public function test_counts_can_be_limited_to_a_tag(): void
    {
        $this->make('S/Maps/a.png', MediaKind::Art, 'S', ['Maps']);
        $this->make('S/Dungeons/b.png', MediaKind::Art, 'S', ['Dungeons']);
        $this->make('S/Maps/c.mp3', MediaKind::Music, 'S', ['Maps']);

        $this->assertSame(['art' => 1, 'music' => 1], MediaItem::countsForSystem('S', 'Maps'));
        $this->assertSame(['art' => 0, 'music' => 0], MediaItem::countsForSystem('S', 'Nope'));
        $this->assertSame(['art' => 2, 'music' => 1], MediaItem::countsForSystem('S'));
    }
}
