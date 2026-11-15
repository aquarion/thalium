<?php

namespace Tests\Feature;

use App\Service\Media\MediaKind;
use App\Service\Media\MediaParserFactory;
use App\Service\Media\ParseAudioService;
use App\Service\Media\ParseImageService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesMediaFiles;
use Tests\TestCase;

class MediaParserTest extends TestCase
{
    use MakesMediaFiles;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('libris');
    }

    public function test_image_parser_reads_dimensions_and_derives_system_and_tags(): void
    {
        Storage::disk('libris')->put('Pathfinder 2e/Maps/City/a.png', $this->png(30, 20));

        $parser = new ParseImageService('Pathfinder 2e/Maps/City/a.png');

        $this->assertSame(MediaKind::Art, $parser->kind());
        $this->assertSame('Pathfinder 2e', $parser->system);
        $this->assertSame(['Maps', 'City'], $parser->tags);
        $this->assertSame('a', $parser->title);
        $this->assertSame(['width' => 30, 'height' => 20], $parser->metadata());
    }

    public function test_image_parser_makes_a_bounded_thumbnail(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick not available');
        }

        Storage::disk('libris')->put('Sys/Maps/big.png', $this->png(1000, 500));

        $thumbnail = (new ParseImageService('Sys/Maps/big.png'))->generateDocThumbnail();

        $this->assertSame(400, $thumbnail->getImageWidth());
        $this->assertSame(200, $thumbnail->getImageHeight());
    }

    public function test_audio_parser_reads_duration(): void
    {
        Storage::disk('libris')->put('Sys/Ambience/rain.wav', $this->wav());

        $parser = new ParseAudioService('Sys/Ambience/rain.wav');

        $this->assertSame(MediaKind::Music, $parser->kind());
        $this->assertSame(1, $parser->metadata()['duration_seconds']);
    }

    public function test_corrupt_media_gives_empty_metadata(): void
    {
        Storage::disk('libris')->put('Sys/Maps/broken.png', '');
        Storage::disk('libris')->put('Sys/Ambience/broken.mp3', '');

        $this->assertSame([], (new ParseImageService('Sys/Maps/broken.png'))->metadata());
        $this->assertSame([], (new ParseAudioService('Sys/Ambience/broken.mp3'))->metadata());
    }

    public function test_factory_picks_parser_by_kind_and_ignores_other_files(): void
    {
        Storage::disk('libris')->put('Sys/a.png', $this->png(1, 1));
        Storage::disk('libris')->put('Sys/b.wav', $this->wav());
        Storage::disk('libris')->put('Sys/c.pdf', '%PDF');

        $this->assertInstanceOf(ParseImageService::class, MediaParserFactory::make('Sys/a.png'));
        $this->assertInstanceOf(ParseAudioService::class, MediaParserFactory::make('Sys/b.wav'));
        $this->assertNull(MediaParserFactory::make('Sys/c.pdf'));
    }

    public function test_audio_parser_reads_id3_tags_including_track_number(): void
    {
        Storage::disk('libris')->put('Sys/Album/03 raw name.mp3', $this->mp3WithTags([
            'TIT2' => 'Deep Hack',
            'TPE1' => 'The Band',
            'TALB' => 'Unhallowed Data Soundtrack',
            'TRCK' => '3/17',
        ]));

        $metadata = (new ParseAudioService('Sys/Album/03 raw name.mp3'))->metadata();

        $this->assertSame('Deep Hack', $metadata['title']);
        $this->assertSame('The Band', $metadata['artist']);
        $this->assertSame('Unhallowed Data Soundtrack', $metadata['album']);
        $this->assertSame(3, $metadata['track_number']);
    }

    public function test_track_number_without_a_total_and_junk_values(): void
    {
        Storage::disk('libris')->put('Sys/Album/a.mp3', $this->mp3WithTags(['TIT2' => 'A', 'TRCK' => '7']));
        Storage::disk('libris')->put('Sys/Album/b.mp3', $this->mp3WithTags(['TIT2' => 'B', 'TRCK' => 'abc']));
        Storage::disk('libris')->put('Sys/Album/c.mp3', $this->mp3WithTags(['TIT2' => 'C', 'TRCK' => '0']));

        $this->assertSame(7, (new ParseAudioService('Sys/Album/a.mp3'))->metadata()['track_number']);
        $this->assertArrayNotHasKey('track_number', (new ParseAudioService('Sys/Album/b.mp3'))->metadata());
        $this->assertArrayNotHasKey('track_number', (new ParseAudioService('Sys/Album/c.mp3'))->metadata());
    }
}
