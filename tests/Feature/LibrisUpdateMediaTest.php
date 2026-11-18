<?php

namespace Tests\Feature;

use App\Jobs\ScanFile;
use App\Libris\LibrisInterface;
use App\Models\MediaItem;
use App\Service\Media\MediaIngestService;
use App\Service\Media\MediaKind;
use App\Service\Media\MediaSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\MakesMediaFiles;
use Tests\TestCase;

class LibrisUpdateMediaTest extends TestCase
{
    use MakesMediaFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('libris');
        Storage::fake('thumbnails');
    }

    private function putFiles(): void
    {
        Storage::disk('libris')->put('SysA/Pictures/a.png', $this->png(1, 1));
        Storage::disk('libris')->put('SysA/Ambience/d.mp3', 'x');
        Storage::disk('libris')->put('SysB/Pictures/e.png', $this->png(1, 1));
        Storage::disk('libris')->put('SysA/b.pdf', '%PDF');
        Storage::disk('libris')->put('.thalium/c.png', $this->png(1, 1));
    }

    private function pushedPaths(): array
    {
        $paths = [];
        Queue::assertPushed(ScanFile::class, function ($job) use (&$paths) {
            $paths[] = (string) $job;

            return true;
        });
        sort($paths);

        return $paths;
    }

    public function test_background_dispatches_only_media_files(): void
    {
        Queue::fake();
        $this->putFiles();

        $this->artisan('libris:update_media')->assertSuccessful();

        $this->assertSame(
            ['ScanFile <SysA/Ambience/d.mp3>', 'ScanFile <SysA/Pictures/a.png>', 'ScanFile <SysB/Pictures/e.png>'],
            $this->pushedPaths()
        );
    }

    public function test_background_skips_files_that_are_already_up_to_date(): void
    {
        Queue::fake();
        $this->putFiles();
        $service = new MediaIngestService;
        $service->ingest('SysA/Pictures/a.png');
        $service->ingest('SysB/Pictures/e.png');

        $this->artisan('libris:update_media')->assertSuccessful();

        $this->assertSame(['ScanFile <SysA/Ambience/d.mp3>'], $this->pushedPaths());
    }

    public function test_start_option_limits_the_walk(): void
    {
        Queue::fake();
        $this->putFiles();

        $this->artisan('libris:update_media', ['--start' => 'SysB'])->assertSuccessful();

        $this->assertSame(['ScanFile <SysB/Pictures/e.png>'], $this->pushedPaths());
    }

    public function test_foreground_ingests_and_prunes(): void
    {
        $this->putFiles();
        MediaItem::create([
            'path' => 'SysA/Pictures/stale.png', 'kind' => MediaKind::Art, 'system' => 'SysA',
            'tags' => ['Pictures'], 'title' => 'stale', 'mtime' => 1, 'size' => 1,
        ]);

        $this->artisan('libris:update_media', ['--foreground' => true])
            ->expectsOutputToContain('Added 3, updated 0, unchanged 0')
            ->expectsOutputToContain('Removed 1 deleted files')
            ->assertSuccessful();

        $this->assertSame(3, MediaItem::count());
        $this->assertNull(MediaItem::where('path', 'SysA/Pictures/stale.png')->first());

        $this->artisan('libris:update_media', ['--foreground' => true])
            ->expectsOutputToContain('Added 0, updated 0, unchanged 3');
    }

    public function test_force_rereads_unchanged_files_in_the_foreground(): void
    {
        Queue::fake();
        Storage::disk('libris')->put('SysA/Album/raw.mp3', $this->mp3WithTags(['TIT2' => 'Real Title', 'TRCK' => '4']));
        $item = (new MediaIngestService)->ingest('SysA/Album/raw.mp3');
        MediaItem::where('id', $item->id)->update(['title' => 'stale', 'track_number' => null]);

        $this->artisan('libris:update_media')->assertSuccessful();
        $this->assertSame('stale', $item->fresh()->title);

        $this->artisan('libris:update_media', ['--force' => true])
            ->expectsOutputToContain('updated 1')
            ->assertSuccessful();

        $this->assertSame(['Real Title', 4], [$item->fresh()->title, $item->fresh()->track_number]);
    }

    public function test_pruning_is_skipped_with_a_warning_when_the_library_looks_empty(): void
    {
        MediaItem::create([
            'path' => 'SysA/Pictures/stale.png', 'kind' => MediaKind::Art, 'system' => 'SysA',
            'tags' => ['Pictures'], 'title' => 'stale', 'mtime' => 1, 'size' => 1,
        ]);

        $this->artisan('libris:update_media', ['--foreground' => true])
            ->expectsOutputToContain('Skipped pruning')
            ->assertFailed(); // so the hourly scheduler flags an unmounted library

        $this->assertSame(1, MediaItem::count());
    }

    private function fakeLibris(): void
    {
        $indexer = Mockery::mock();
        $indexer->shouldReceive('setup')->once();
        $this->mock(LibrisInterface::class)->shouldReceive('getIndexer')->andReturn($indexer);
    }

    public function test_libris_update_also_prunes_deleted_media(): void
    {
        Queue::fake();
        $this->fakeLibris();
        $this->putFiles();
        MediaItem::create([
            'path' => 'SysA/Pictures/stale.png', 'kind' => MediaKind::Art, 'system' => 'SysA',
            'tags' => ['Pictures'], 'title' => 'stale', 'mtime' => 1, 'size' => 1,
        ]);

        $this->artisan('libris:update')
            ->expectsOutputToContain('Removed 1 deleted files')
            ->assertSuccessful();

        $this->assertSame(0, MediaItem::count());
    }

    public function test_libris_update_fails_without_pruning_when_the_library_looks_empty(): void
    {
        Queue::fake();
        $this->fakeLibris();
        MediaItem::create([
            'path' => 'SysA/Pictures/stale.png', 'kind' => MediaKind::Art, 'system' => 'SysA',
            'tags' => ['Pictures'], 'title' => 'stale', 'mtime' => 1, 'size' => 1,
        ]);

        $this->artisan('libris:update')
            ->expectsOutputToContain('Skipped pruning')
            ->assertFailed(); // so the daily scheduler flags an unmounted library

        $this->assertSame(1, MediaItem::count());
    }

    public function test_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('libris:update_media');
    }

    public function test_update_media_ingests_the_maps_tree(): void
    {
        Storage::fake('maps');
        config(['filesystems.disks.maps.root' => Storage::disk('maps')->path('')]);
        Storage::disk('maps')->put('BattleMats/Day/a.png', $this->png(1, 1));
        Storage::disk('maps')->put('BattleMats/b.zip', 'x');

        $this->artisan('libris:update_media', ['--foreground' => true])->assertSuccessful();

        $item = MediaItem::where('path', 'BattleMats/Day/a.png')->first();
        $this->assertSame(MediaSource::Maps, $item->source);
        $this->assertSame(1, MediaItem::count());
    }

    public function test_update_media_skips_an_unconfigured_maps_source(): void
    {
        config(['filesystems.disks.maps.root' => null]);
        $this->putFiles();

        $this->artisan('libris:update_media', ['--foreground' => true])->assertSuccessful();

        $this->assertSame(0, MediaItem::where('source', 'maps')->count());
        $this->assertSame(3, MediaItem::count());
    }

    public function test_an_empty_maps_root_does_not_fail_the_run_or_touch_library_rows(): void
    {
        Storage::fake('maps');
        config(['filesystems.disks.maps.root' => Storage::disk('maps')->path('')]);
        $this->putFiles();

        $this->artisan('libris:update_media', ['--foreground' => true])->assertSuccessful();

        $this->assertSame(3, MediaItem::count());
    }
}
