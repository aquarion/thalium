<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use App\Service\Media\MediaSource;
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

        $this->assertSame(['art' => 2, 'music' => 1, 'map' => 0], MediaItem::countsForSystem('S'));
        $this->assertSame(['art' => 0, 'music' => 0, 'map' => 0], MediaItem::countsForSystem('Nope'));
        $this->assertSame(['City' => 1, 'Maps' => 2], MediaItem::tagCounts(MediaKind::Art, 'S'));
    }

    public function test_systems_with_media(): void
    {
        $this->make('S/a.png', MediaKind::Art, 'S');
        $this->make('S/b.mp3', MediaKind::Music, 'S');
        $this->make('Other/d.png', MediaKind::Art, 'Other');

        $systems = MediaItem::systemsWithMedia();

        $this->assertSame(['art' => 1, 'music' => 1, 'map' => 0], $systems['S']);
        $this->assertSame(['art' => 1, 'music' => 0, 'map' => 0], $systems['Other']);
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

        $this->assertSame(['art' => 1, 'music' => 1, 'map' => 0], MediaItem::countsForSystem('S', 'Maps'));
        $this->assertSame(['art' => 0, 'music' => 0, 'map' => 0], MediaItem::countsForSystem('S', 'Nope'));
        $this->assertSame(['art' => 2, 'music' => 1, 'map' => 0], MediaItem::countsForSystem('S'));
    }

    public function test_same_path_in_both_sources_makes_two_rows(): void
    {
        foreach ([MediaSource::Library, MediaSource::Maps] as $source) {
            MediaItem::create([
                'path' => 'A/x.png', 'kind' => MediaKind::Map, 'source' => $source, 'system' => null,
                'collection' => 'A', 'tags' => [], 'title' => 'x', 'mtime' => 1, 'size' => 1,
            ]);
        }

        $this->assertSame(2, MediaItem::count());
    }

    public function test_library_hash_is_unchanged(): void
    {
        $this->assertSame(sha1('A/x.png'), MediaItem::hashPath('A/x.png'));
        $this->assertNotSame(sha1('A/x.png'), MediaItem::hashPath('A/x.png', MediaSource::Maps));
    }

    public function test_maps_url_uses_the_maps_disk(): void
    {
        Storage::fake('maps');
        $item = MediaItem::create([
            'path' => 'Battle Mats/a.png', 'kind' => MediaKind::Map, 'source' => MediaSource::Maps,
            'collection' => 'Battle Mats', 'tags' => [], 'title' => 'a', 'mtime' => 1, 'size' => 1,
        ]);

        $this->assertStringContainsString('Battle%20Mats/a.png', $item->url());
        $this->assertSame(MediaSource::Maps, $item->fresh()->source);
    }

    public function test_system_can_be_null_and_source_defaults_to_library(): void
    {
        $item = MediaItem::create([
            'path' => 'Z/a.png', 'kind' => MediaKind::Map, 'collection' => 'Z', 'tags' => [],
            'title' => 'a', 'mtime' => 1, 'size' => 1,
        ]);

        $this->assertNull($item->fresh()->system);
        $this->assertSame(MediaSource::Library, $item->fresh()->source);
    }

    private function map(string $path, ?string $system, ?string $collection, array $tags = [], MediaSource $source = MediaSource::Library, string $title = 'm'): MediaItem
    {
        return MediaItem::create([
            'path' => $path, 'source' => $source, 'kind' => MediaKind::Map, 'system' => $system,
            'collection' => $collection, 'tags' => $tags, 'title' => $title, 'mtime' => 1, 'size' => 1,
        ]);
    }

    public function test_forSystem_matches_collection_case_insensitively_only_for_the_maps_source(): void
    {
        $this->map('S/Maps/a.png', 'S', null);
        $this->map('blades in the dark/b.png', null, 'blades in the dark', [], MediaSource::Maps);
        $this->map('BattleMats/c.png', null, 'BattleMats', [], MediaSource::Maps);
        // A library row never matches on collection.
        $this->map('Other/Maps/d.png', 'Other', 'Blades In The Dark');

        $paths = MediaItem::query()->forSystem('Blades In The Dark')->pluck('path')->all();

        $this->assertSame(['blades in the dark/b.png'], $paths);
        $this->assertSame(['S/Maps/a.png'], MediaItem::query()->forSystem('S')->pluck('path')->all());
    }

    public function test_counts_include_maps_matched_by_collection(): void
    {
        $this->make('S/Pictures/a.png', MediaKind::Art, 'S');
        $this->map('S/Maps/m.png', 'S', null, ['City']);
        $this->map('S/b.png', null, 'S', ['City'], MediaSource::Maps);
        $this->map('BattleMats/c.png', null, 'BattleMats', [], MediaSource::Maps);

        $this->assertSame(['art' => 1, 'music' => 0, 'map' => 2], MediaItem::countsForSystem('S'));
        $this->assertSame(['art' => 0, 'music' => 0, 'map' => 2], MediaItem::countsForSystem('S', 'City'));
        $this->assertSame(['City' => 2], MediaItem::tagCounts(MediaKind::Map, 'S'));
    }

    public function test_systemsWithMedia_ignores_null_system(): void
    {
        $this->make('S/Pictures/a.png', MediaKind::Art, 'S');
        $this->map('BattleMats/c.png', null, 'BattleMats', [], MediaSource::Maps);

        $this->assertSame(['S'], MediaItem::systemsWithMedia()->keys()->all());
        $this->assertSame(['art' => 1, 'music' => 0, 'map' => 0], MediaItem::systemsWithMedia()['S']);
    }

    public function test_mapGroups_groups_by_system_or_collection_and_filters_by_tag(): void
    {
        $this->map('Z/Maps/z.png', 'Z', null, ['Day'], MediaSource::Library, 'Zed');
        $this->map('A/a.png', null, 'A', ['Night'], MediaSource::Maps, 'Alpha');
        $this->map('A/b.png', null, 'A', ['Day'], MediaSource::Maps, 'Bravo');
        $this->make('Z/Pictures/art.png', MediaKind::Art, 'Z');

        $groups = MediaItem::mapGroups();

        $this->assertSame(['A', 'Z'], $groups->keys()->all());
        $this->assertSame(['Alpha', 'Bravo'], $groups['A']->pluck('title')->all());
        $this->assertSame(['A', 'Z'], MediaItem::mapGroups('Day')->keys()->all());
        $this->assertSame(['Bravo'], MediaItem::mapGroups('Day')['A']->pluck('title')->all());
        $this->assertSame(['Day' => 2, 'Night' => 1], MediaItem::mapTagCounts());
    }

    public function test_agnostic_collection_is_never_listed_as_a_system_with_media(): void
    {
        $this->map('BattleMats/c.png', null, 'BattleMats', [], MediaSource::Maps);

        $this->assertNotContains('BattleMats', MediaItem::systemsWithMedia()->keys()->all());
    }
}
