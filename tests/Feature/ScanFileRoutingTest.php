<?php

namespace Tests\Feature;

use App\Interfaces\IndexerInterface;
use App\Libris\LibrisService;
use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use App\Service\Media\MediaSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
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
        Storage::disk('libris')->put('Sys/Pictures/a.png', $this->png(30, 20));

        // Any call into Elasticsearch here would fail the test.
        $result = (new LibrisService)->scanFile('Sys/Pictures/a.png');

        $this->assertSame(0, $result);
        $item = MediaItem::where('path', 'Sys/Pictures/a.png')->first();
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

    private function serviceWithIndexer(MockInterface $indexer): LibrisService
    {
        $service = new LibrisService;
        $property = new \ReflectionProperty($service, 'indexer');
        $property->setValue($service, $indexer);

        return $service;
    }

    public function test_map_pdf_is_ingested_and_removed_from_elasticsearch(): void
    {
        Storage::disk('libris')->put('Sys/Maps/city.pdf', '%PDF-1.4');
        $indexer = \Mockery::mock(IndexerInterface::class);
        $indexer->shouldReceive('fetchDocument')->with('Sys/Maps/city.pdf')->once()->andReturn(['_id' => 'doc-1']);
        $indexer->shouldReceive('deleteDocument')->with('doc-1')->once();
        $indexer->shouldNotReceive('indexDocument');

        $result = $this->serviceWithIndexer($indexer)->scanFile('Sys/Maps/city.pdf');

        $this->assertSame(0, $result);
        $this->assertSame(MediaKind::Map, MediaItem::where('path', 'Sys/Maps/city.pdf')->first()->kind);
    }

    public function test_map_pdf_not_yet_indexed_is_just_ingested(): void
    {
        Storage::disk('libris')->put('Sys/Maps/city.pdf', '%PDF-1.4');
        $indexer = \Mockery::mock(IndexerInterface::class);
        $indexer->shouldReceive('fetchDocument')->andReturn(false);
        $indexer->shouldNotReceive('deleteDocument');

        $this->assertSame(0, $this->serviceWithIndexer($indexer)->scanFile('Sys/Maps/city.pdf'));
        $this->assertSame(1, MediaItem::count());
    }

    public function test_image_under_maps_is_a_map_not_art(): void
    {
        Storage::disk('libris')->put('Sys/Maps/a.png', $this->png(30, 20));

        (new LibrisService)->scanFile('Sys/Maps/a.png');

        $this->assertSame(MediaKind::Map, MediaItem::where('path', 'Sys/Maps/a.png')->first()->kind);
    }

    public function test_maps_tree_file_is_ingested_from_the_maps_disk(): void
    {
        Storage::fake('maps');
        Storage::disk('maps')->put('BattleMats/a.png', $this->png(30, 20));

        $this->assertSame(0, (new LibrisService)->scanFile('BattleMats/a.png', 'maps'));

        $item = MediaItem::where('path', 'BattleMats/a.png')->first();
        $this->assertSame(MediaSource::Maps, $item->source);
        $this->assertSame('BattleMats', $item->collection);
    }

    public function test_adding_a_map_pdf_directly_does_not_index_it(): void
    {
        Storage::disk('libris')->put('Sys/Maps/city.pdf', '%PDF-1.4');
        $indexer = \Mockery::mock(IndexerInterface::class);
        $indexer->shouldReceive('fetchDocument')->andReturn(false);
        $indexer->shouldNotReceive('indexDocument');

        $this->serviceWithIndexer($indexer)->addDocument('Sys/Maps/city.pdf');

        $this->assertSame(MediaKind::Map, MediaItem::where('path', 'Sys/Maps/city.pdf')->first()->kind);
    }
}
