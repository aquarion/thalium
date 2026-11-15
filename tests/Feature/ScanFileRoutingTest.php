<?php

namespace Tests\Feature;

use App\Libris\LibrisService;
use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesMediaFiles;
use Tests\TestCase;

class ScanFileRoutingTest extends TestCase
{
    use MakesMediaFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('libris');
        Storage::fake('thumbnails');
    }

    public function test_image_is_ingested_not_indexed(): void
    {
        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(30, 20));

        // Any call into Elasticsearch here would fail the test.
        $result = (new LibrisService)->scanFile('Sys/Maps/a.png');

        $this->assertSame(0, $result);
        $item = MediaItem::where('path', 'Sys/Maps/a.png')->first();
        $this->assertSame(MediaKind::Art, $item->kind);
        $this->assertSame('Sys', $item->system);
    }

    public function test_music_is_ingested_not_indexed(): void
    {
        Storage::disk('libris')->put('Sys/Ambience/rain.wav', $this->wav());

        $this->assertSame(0, (new LibrisService)->scanFile('Sys/Ambience/rain.wav'));
        $this->assertSame(MediaKind::Music, MediaItem::where('path', 'Sys/Ambience/rain.wav')->first()->kind);
    }

    public function test_hidden_media_is_quietly_ignored(): void
    {
        Storage::disk('libris')->put('.thalium/pathfinder.png', $this->png(1, 1));

        $this->assertSame(0, (new LibrisService)->scanFile('.thalium/pathfinder.png'));
        $this->assertSame(0, MediaItem::count());
    }
}
