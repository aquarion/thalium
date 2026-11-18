<?php

namespace Tests\Unit;

use App\Service\Media\MediaClassifier;
use App\Service\Media\MediaKind;
use App\Service\Media\MediaSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaClassifierTest extends TestCase
{
    public static function classifications(): array
    {
        $L = MediaSource::Library;
        $M = MediaSource::Maps;

        return [
            'deep maps folder, any case' => [$L, 'S/Dungeons/MAPS/Deep/a.pdf', MediaKind::Map, 'S', null, ['Dungeons', 'Deep']],
            'lowercase maps folder' => [$L, 'S/maps/a.png', MediaKind::Map, 'S', null, []],
            'plain art' => [$L, 'S/Dungeons/a.png', MediaKind::Art, 'S', null, ['Dungeons']],
            'music' => [$L, 'S/Ambience/rain.wav', MediaKind::Music, 'S', null, ['Ambience']],
            'system normalised' => [$L, 'Blades_in-the Dark/Maps/a.jpg', MediaKind::Map, 'Blades in the Dark', null, []],
            'a system named Maps is not a maps folder' => [$L, 'Maps/a.png', MediaKind::Art, 'Maps', null, []],
            'nested Maps segments both dropped' => [$L, 'S/Maps/Maps/a.png', MediaKind::Map, 'S', null, []],
            'tree: collection and tags' => [$M, 'Blades_in-the Dark/Buildings/012 Library/a.jpg', MediaKind::Map, null, 'Blades in the Dark', ['Buildings', '012 Library']],
            'tree: pdf directly under collection' => [$M, 'BattleMats/a.pdf', MediaKind::Map, null, 'BattleMats', []],
        ];
    }

    #[DataProvider('classifications')]
    public function test_classifies(MediaSource $source, string $path, MediaKind $kind, ?string $system, ?string $collection, array $tags): void
    {
        $result = MediaClassifier::classify($source, $path);

        $this->assertNotNull($result);
        $this->assertSame($kind, $result->kind);
        $this->assertSame($system, $result->system);
        $this->assertSame($collection, $result->collection);
        $this->assertSame($tags, $result->tags);
    }

    public static function ignored(): array
    {
        return [
            'pdf outside a maps folder is a document' => [MediaSource::Library, 'S/a.pdf'],
            'file named Maps.pdf' => [MediaSource::Library, 'S/Maps.pdf'],
            'dot directory' => [MediaSource::Library, 'S/.thalium/Maps/a.png'],
            'unsupported type in maps folder' => [MediaSource::Library, 'S/Maps/a.psd'],
            'library root file' => [MediaSource::Library, 'a.png'],
            'tree: root file' => [MediaSource::Maps, 'a.jpg'],
            'tree: dot directory' => [MediaSource::Maps, 'BattleMats/.hidden/a.jpg'],
            'tree: unsupported type' => [MediaSource::Maps, 'BattleMats/a.zip'],
            'tree: audio is not a map' => [MediaSource::Maps, 'BattleMats/a.mp3'],
        ];
    }

    #[DataProvider('ignored')]
    public function test_ignores(MediaSource $source, string $path): void
    {
        $this->assertNull(MediaClassifier::classify($source, $path));
    }

    public function test_is_map_path_is_true_only_for_library_pdfs_and_images_in_maps_folders(): void
    {
        $this->assertTrue(MediaClassifier::isMapPath('S/Maps/a.pdf'));
        $this->assertTrue(MediaClassifier::isMapPath('S/x/maps/a.png'));
        $this->assertFalse(MediaClassifier::isMapPath('S/a.pdf'));
        $this->assertFalse(MediaClassifier::isMapPath('Maps/a.pdf'));
        $this->assertFalse(MediaClassifier::isMapPath('S/Maps/a.mp3'));
    }
}
