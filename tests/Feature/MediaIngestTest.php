<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Service\Media\MediaIngestService;
use App\Service\Media\MediaKind;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesMediaFiles;
use Tests\TestCase;

class MediaIngestTest extends TestCase
{
    use MakesMediaFiles;
    use RefreshDatabase;

    private MediaIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('libris');
        Storage::fake('thumbnails');
        $this->service = new MediaIngestService;
    }

    public function test_ingest_creates_rows_with_derived_fields(): void
    {
        Storage::disk('libris')->put('Pathfinder 2e/Maps/City/a.png', $this->png(30, 20));
        Storage::disk('libris')->put('Pathfinder 2e/Ambience/rain.WAV', $this->wav());

        $art = $this->service->ingest('Pathfinder 2e/Maps/City/a.png');
        $music = $this->service->ingest('Pathfinder 2e/Ambience/rain.WAV');

        $this->assertSame(MediaKind::Art, $art->kind);
        $this->assertSame('Pathfinder 2e', $art->system);
        $this->assertSame(['Maps', 'City'], $art->tags);
        $this->assertSame('a', $art->title);
        $this->assertSame([30, 20], [$art->width, $art->height]);
        $this->assertSame(MediaKind::Music, $music->kind);
        $this->assertSame(1, $music->duration_seconds);
        $this->assertSame(2, MediaItem::count());
    }

    public function test_ingest_makes_a_thumbnail_on_the_thumbnails_disk(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick not available');
        }

        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(30, 20));

        $item = $this->service->ingest('Sys/Maps/a.png');

        $this->assertSame(md5('Sys/Maps/a.png').'.png', $item->thumbnail);
        Storage::disk('thumbnails')->assertExists($item->thumbnail);
    }

    public function test_unchanged_mtime_is_skipped_and_a_change_updates(): void
    {
        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(30, 20));
        $first = $this->service->ingest('Sys/Maps/a.png');
        MediaItem::where('id', $first->id)->update(['title' => 'edited by hand']);

        $this->assertSame('edited by hand', $this->service->ingest('Sys/Maps/a.png')->title);

        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(60, 40));
        touch(Storage::disk('libris')->path('Sys/Maps/a.png'), time() + 100);
        $updated = $this->service->ingest('Sys/Maps/a.png');

        $this->assertSame($first->id, $updated->id);
        $this->assertSame([60, 40], [$updated->width, $updated->height]);
        $this->assertSame(1, MediaItem::count());
    }

    public function test_corrupt_files_still_get_a_row(): void
    {
        Storage::disk('libris')->put('Sys/Ambience/Broken_Track.mp3', '');
        Storage::disk('libris')->put('Sys/Maps/broken.png', '');

        $music = $this->service->ingest('Sys/Ambience/Broken_Track.mp3');
        $art = $this->service->ingest('Sys/Maps/broken.png');

        $this->assertSame('Broken Track', $music->title);
        $this->assertNull($music->duration_seconds);
        $this->assertNull($art->width);
        $this->assertSame(2, MediaItem::count());
    }

    public function test_hidden_and_root_files_are_ignored(): void
    {
        foreach (['.thalium/x.png', 'Sys/.hidden.png', 'Sys/.cache/y.png', 'loose.png'] as $path) {
            Storage::disk('libris')->put($path, $this->png(1, 1));
            $this->assertNull($this->service->ingest($path), $path);
        }

        $this->assertSame(0, MediaItem::count());
    }

    public function test_ingest_returns_null_when_the_file_has_vanished(): void
    {
        $this->assertNull($this->service->ingest('Sys/Maps/gone.png'));
        $this->assertSame(0, MediaItem::count());
    }

    public function test_prune_removes_rows_and_thumbnails_for_deleted_files(): void
    {
        Storage::disk('libris')->put('Sys/Maps/keep.png', $this->png(1, 1));
        Storage::disk('libris')->put('Sys/Maps/drop.png', $this->png(1, 1));
        $this->service->ingest('Sys/Maps/keep.png');
        $drop = $this->service->ingest('Sys/Maps/drop.png');
        $drop->update(['thumbnail' => 'drop-thumb.png']);
        Storage::disk('thumbnails')->put('drop-thumb.png', 'x');
        Storage::disk('libris')->delete('Sys/Maps/drop.png');

        $this->assertSame(1, $this->service->prune());
        $this->assertSame(['Sys/Maps/keep.png'], MediaItem::pluck('path')->all());
        Storage::disk('thumbnails')->assertMissing('drop-thumb.png');
    }

    public function test_paths_yields_only_ingestible_files(): void
    {
        foreach (['Sys/a.png', 'Sys/Deep/b.mp3', 'Sys/c.pdf', '.thalium/d.png', 'e.png'] as $path) {
            Storage::disk('libris')->put($path, 'x');
        }

        $paths = iterator_to_array($this->service->paths(), false);
        sort($paths);

        $this->assertSame(['Sys/Deep/b.mp3', 'Sys/a.png'], $paths);
    }

    public function test_ingest_stores_tags_and_track_number(): void
    {
        Storage::disk('libris')->put('Sys/Album/raw.mp3', $this->mp3WithTags([
            'TIT2' => 'Deep Hack', 'TPE1' => 'The Band', 'TALB' => 'The Album', 'TRCK' => '3/17',
        ]));

        $item = $this->service->ingest('Sys/Album/raw.mp3');

        $this->assertSame(
            ['Deep Hack', 'The Band', 'The Album', 3],
            [$item->title, $item->artist, $item->album, $item->track_number]
        );
    }

    public function test_force_rereads_a_file_whose_mtime_has_not_changed(): void
    {
        Storage::disk('libris')->put('Sys/Album/raw.mp3', $this->mp3WithTags(['TIT2' => 'Real Title', 'TRCK' => '2']));
        $item = $this->service->ingest('Sys/Album/raw.mp3');
        MediaItem::where('id', $item->id)->update(['title' => 'stale', 'track_number' => null]);

        $this->assertSame('stale', $this->service->ingest('Sys/Album/raw.mp3')->title);

        $forced = $this->service->ingest('Sys/Album/raw.mp3', true);

        $this->assertSame('Real Title', $forced->title);
        $this->assertSame(2, $forced->track_number);
    }

    public function test_prune_refuses_to_delete_everything_when_the_library_looks_empty(): void
    {
        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(1, 1));
        $this->service->ingest('Sys/Maps/a.png');

        // An unmounted or emptied library root, not a library that lost its media.
        Storage::disk('libris')->deleteDirectory('Sys');

        $this->assertNull($this->service->prune());
        $this->assertSame(1, MediaItem::count());
    }

    public function test_prune_still_works_when_other_systems_remain(): void
    {
        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(1, 1));
        Storage::disk('libris')->put('Other/Maps/b.png', $this->png(1, 1));
        $this->service->ingest('Sys/Maps/a.png');
        $this->service->ingest('Other/Maps/b.png');
        Storage::disk('libris')->delete('Sys/Maps/a.png');

        $this->assertSame(1, $this->service->prune());
        $this->assertSame(['Other/Maps/b.png'], MediaItem::pluck('path')->all());
    }

    public function test_over_long_tag_text_is_truncated_to_the_column_size(): void
    {
        // MySQL (strict) rejects text longer than VARCHAR(255); SQLite would not notice.
        Storage::disk('libris')->put('Sys/Album/long.mp3', $this->mp3WithTags([
            'TIT2' => str_repeat('t', 400),
            'TPE1' => str_repeat('a', 400),
            'TALB' => str_repeat('b', 400),
        ]));

        $item = $this->service->ingest('Sys/Album/long.mp3');

        $this->assertSame(255, mb_strlen($item->title));
        $this->assertSame(255, mb_strlen($item->artist));
        $this->assertSame(255, mb_strlen($item->album));
    }

    public function test_a_failed_thumbnail_write_is_not_recorded(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick not available');
        }

        // With the default `throw => false`, a failed put() returns false instead of throwing.
        $broken = \Mockery::mock(Filesystem::class);
        $broken->shouldReceive('put')->andReturn(false);
        Storage::set('thumbnails', $broken);

        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(30, 20));

        $item = $this->service->ingest('Sys/Maps/a.png');

        $this->assertNotNull($item);
        $this->assertNull($item->thumbnail);
    }
}
