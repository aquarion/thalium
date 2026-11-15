<?php

namespace Tests\Feature;

use App\Jobs\ScanFile;
use App\Models\MediaItem;
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

    public function test_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('libris:update_media');
    }
}
