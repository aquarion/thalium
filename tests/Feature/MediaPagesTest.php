<?php

namespace Tests\Feature;

use App\Libris\LibrisInterface;
use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class MediaPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('libris');
        Storage::fake('thumbnails');

        $this->mock(LibrisInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('docsBySystem')->andReturn(['hits' => ['total' => ['value' => 5], 'hits' => []]]);
            $mock->shouldReceive('tagsForSystem')->andReturn([]);
            $mock->shouldReceive('systems')->andReturn([]);
            $mock->shouldReceive('getSystemThumbnail')->andReturn('data:image/png;base64,AA==');
        });
    }

    private function login(): void
    {
        $this->actingAs(UserFactory::new()->create());
    }

    private function item(string $path, MediaKind $kind, string $system, array $tags, string $title, array $extra = []): MediaItem
    {
        return MediaItem::create(array_merge([
            'path' => $path, 'kind' => $kind, 'system' => $system, 'tags' => $tags,
            'title' => $title, 'mtime' => 1, 'size' => 1,
        ], $extra));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/system/S/art')->assertRedirect(route('login'));
        $this->get('/system/S/music')->assertRedirect(route('login'));
    }

    public function test_art_page_lists_thumbnails_and_filters_by_tag(): void
    {
        $this->login();
        $this->item('S/Maps/a.png', MediaKind::Art, 'S', ['Maps'], 'Alpha', ['thumbnail' => 'alpha.png']);
        $this->item('S/Dungeons/b.png', MediaKind::Art, 'S', ['Dungeons'], 'Beta');

        $this->get('/system/S/art')
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('alpha.png')
            ->assertSee('Maps')
            ->assertSee('Dungeons');

        $this->get('/system/S/art?tag=Maps')
            ->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Beta');
    }

    public function test_music_page_lists_tracks_with_encoded_urls(): void
    {
        $this->login();
        $this->item('S/Ambience/Rain #1.mp3', MediaKind::Music, 'S', ['Ambience'], 'Rain', [
            'artist' => 'Weather', 'duration_seconds' => 75,
        ]);

        $this->get('/system/S/music')
            ->assertOk()
            ->assertSee('data-track-url', false)
            ->assertSee('Rain%20%231.mp3', false)
            ->assertSee('Weather')
            ->assertSee('1:15');
    }

    public function test_empty_system_renders_with_an_empty_state(): void
    {
        $this->login();

        $this->get('/system/Nope/art')->assertOk()->assertSee('No art in this section yet.');
        $this->get('/system/Nope/music')->assertOk()->assertSee('No music in this section yet.');
    }

    public function test_tab_partial_marks_the_active_tab_and_dims_empty_ones(): void
    {
        $html = view('partials.system-tabs', [
            'system' => 'S',
            'active' => 'art',
            'counts' => ['documents' => 0, 'art' => 3, 'music' => 2],
        ])->render();

        preg_match_all('/<a class="([^"]*)" href="([^"]*)"[^>]*>\s*(Documents|Art|Music)/', $html, $m, PREG_SET_ORDER);
        $tabs = [];
        foreach ($m as $match) {
            $tabs[$match[3]] = ['class' => $match[1], 'href' => $match[2]];
        }

        $this->assertSame(['Documents', 'Art', 'Music'], array_keys($tabs));
        $this->assertSame(route('system.index', ['system' => 'S']), $tabs['Documents']['href']);
        $this->assertSame(route('system.art', ['system' => 'S']), $tabs['Art']['href']);
        $this->assertSame(route('system.music', ['system' => 'S']), $tabs['Music']['href']);
        $this->assertStringContainsString('active', $tabs['Art']['class']);
        $this->assertStringNotContainsString('active', $tabs['Music']['class']);
        $this->assertStringContainsString('disabled-tab', $tabs['Documents']['class']);
        $this->assertStringNotContainsString('disabled-tab', $tabs['Art']['class']);
    }

    public function test_documents_page_shows_the_tabs_with_counts(): void
    {
        $this->login();
        $this->item('S/a.png', MediaKind::Art, 'S', [], 'A');
        $this->item('S/b.png', MediaKind::Art, 'S', [], 'B');

        $this->get('/system/S')
            ->assertOk()
            ->assertSee('Documents')
            ->assertSee('Art')
            ->assertSee('(5)')
            ->assertSee('(2)')
            ->assertSee(route('system.music', ['system' => 'S']), false);
    }

    public function test_home_includes_media_only_systems(): void
    {
        $this->login();
        $this->item('OnlyArt/a.png', MediaKind::Art, 'OnlyArt', [], 'A');

        $this->get('/')->assertOk()->assertSee('OnlyArt');
    }
}
