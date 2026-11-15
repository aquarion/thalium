<?php

namespace Tests\Feature;

use App\Jobs\ScanFile;
use App\Models\MediaItem;
use App\Service\Media\MediaIngestService;
use App\Service\Media\MediaKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        Storage::disk('libris')->put('SysA/Maps/a.png', $this->png(1, 1));
        Storage::disk('libris')->put('SysA/Ambience/d.mp3', 'x');
        Storage::disk('libris')->put('SysB/Maps/e.png', $this->png(1, 1));
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
            ['ScanFile <SysA/Ambience/d.mp3>', 'ScanFile <SysA/Maps/a.png>', 'ScanFile <SysB/Maps/e.png>'],
            $this->pushedPaths()
        );
    }

    public function test_background_skips_files_that_are_already_up_to_date(): void
    {
        Queue::fake();
        $this->putFiles();
        $service = new MediaIngestService;
        $service->ingest('SysA/Maps/a.png');
        $service->ingest('SysB/Maps/e.png');

        $this->artisan('libris:update_media')->assertSuccessful();

        $this->assertSame(['ScanFile <SysA/Ambience/d.mp3>'], $this->pushedPaths());
    }

    public function test_start_option_limits_the_walk(): void
    {
        Queue::fake();
        $this->putFiles();

        $this->artisan('libris:update_media', ['--start' => 'SysB'])->assertSuccessful();

        $this->assertSame(['ScanFile <SysB/Maps/e.png>'], $this->pushedPaths());
    }

    public function test_foreground_ingests_and_prunes(): void
    {
        $this->putFiles();
        MediaItem::create([
            'path' => 'SysA/Maps/stale.png', 'kind' => MediaKind::Art, 'system' => 'SysA',
            'tags' => ['Maps'], 'title' => 'stale', 'mtime' => 1, 'size' => 1,
        ]);

        $this->artisan('libris:update_media', ['--foreground' => true])
            ->expectsOutputToContain('Added 3, updated 0, unchanged 0')
            ->expectsOutputToContain('Removed 1 deleted files')
            ->assertSuccessful();

        $this->assertSame(3, MediaItem::count());
        $this->assertNull(MediaItem::where('path', 'SysA/Maps/stale.png')->first());

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
            'path' => 'SysA/Maps/stale.png', 'kind' => MediaKind::Art, 'system' => 'SysA',
            'tags' => ['Maps'], 'title' => 'stale', 'mtime' => 1, 'size' => 1,
        ]);

        $this->artisan('libris:update_media', ['--foreground' => true])
            ->expectsOutputToContain('Skipped pruning')
            ->assertFailed(); // so the hourly scheduler flags an unmounted library

        $this->assertSame(1, MediaItem::count());
    }

    public function test_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('libris:update_media');
    }
}
