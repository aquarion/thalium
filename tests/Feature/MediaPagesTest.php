<?php

namespace Tests\Feature;

use App\Libris\LibrisInterface;
use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'artist' => 'Weather', 'album' => 'Storms', 'duration_seconds' => 75,
        ]);

        $this->get('/system/S/music')
            ->assertOk()
            ->assertSee('data-track-url', false)
            ->assertSee('Rain%20%231.mp3', false)
            ->assertSee('Weather')
            ->assertSee('Storms')
            ->assertSee('>Artist<', false)
            ->assertSee('>Album<', false)
            ->assertSee('1:15')
            // Bootstrap 5: `badge-secondary` is a v4 class and renders white-on-nothing.
            ->assertSee('class="badge text-bg-secondary text-decoration-none"', false)
            ->assertDontSee('badge-secondary', false);
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

    public function test_documents_count_covers_every_document_not_just_untagged(): void
    {
        $this->login();
        $this->mock(LibrisInterface::class, function (MockInterface $mock) {
            // The PDF page itself lists untagged documents (tag === 0)...
            $mock->shouldReceive('docsBySystem')
                ->with('S', \Mockery::any(), \Mockery::any(), \Mockery::on(fn ($tag) => $tag === 0))
                ->andReturn(['hits' => ['total' => ['value' => 5], 'hits' => []]]);
            // ...but the tab count must ask for all of them (tag === false = no filter).
            $mock->shouldReceive('docsBySystem')
                ->with('S', \Mockery::any(), \Mockery::any(), \Mockery::on(fn ($tag) => $tag === false))
                ->andReturn(['hits' => ['total' => ['value' => 350], 'hits' => []]]);
            $mock->shouldReceive('tagsForSystem')->andReturn([]);
        });

        $this->get('/system/S')->assertOk()->assertSee('(350)');
        $this->get('/system/S/art')->assertOk()->assertSee('(350)');
    }

    public function test_in_system_links_are_swapped_in_page_so_music_keeps_playing(): void
    {
        $this->login();
        $this->mock(LibrisInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('docsBySystem')->andReturn(['hits' => ['total' => ['value' => 3], 'hits' => []]]);
            $mock->shouldReceive('tagsForSystem')->andReturn([['key' => 'Core', 'doc_count' => 3]]);
        });
        $this->item('S/a.png', MediaKind::Art, 'S', ['Maps'], 'A');

        // Documents tag chips
        $html = $this->get('/system/S')->assertOk()->getContent();
        preg_match_all('/<a class="tag"[^>]*>/', $html, $chips);
        $this->assertNotEmpty($chips[0]);
        foreach ($chips[0] as $chip) {
            $this->assertStringContainsString('data-section-link', $chip);
        }

        // The breadcrumb link back to the system, on the new pages
        $link = 'href="'.route('system.index', ['system' => 'S']).'" data-section-link';
        $this->get('/system/S/art')->assertSee($link, false);
        $this->get('/system/S/music')->assertSee($link, false);
    }

    public function test_tabs_carry_the_current_tag_to_every_section(): void
    {
        $this->login();
        $this->item('S/Maps/a.png', MediaKind::Art, 'S', ['Maps'], 'A');

        $carried = [
            route('system.index', ['system' => 'S', 'tag' => 'Maps']),
            route('system.art', ['system' => 'S', 'tag' => 'Maps']),
            route('system.music', ['system' => 'S', 'tag' => 'Maps']),
        ];

        foreach (['/system/S/art?tag=Maps', '/system/S/music?tag=Maps', '/system/S?tag=Maps'] as $url) {
            $response = $this->get($url)->assertOk();
            foreach ($carried as $href) {
                $response->assertSee('href="'.$href.'"', false);
            }
        }

        // Without a tag, the tabs stay plain.
        $this->get('/system/S/art')->assertOk()
            ->assertSee('href="'.route('system.music', ['system' => 'S']).'"', false)
            ->assertDontSee('href="'.route('system.music', ['system' => 'S', 'tag' => 'Maps']).'"', false);
    }

    public function test_tab_counts_follow_the_carried_tag(): void
    {
        $this->login();
        $this->item('S/Maps/a.png', MediaKind::Art, 'S', ['Maps'], 'A');
        $this->item('S/Dungeons/b.png', MediaKind::Art, 'S', ['Dungeons'], 'B');
        $this->item('S/Maps/c.mp3', MediaKind::Music, 'S', ['Maps'], 'C');
        $this->mock(LibrisInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('docsBySystem')
                ->with('S', \Mockery::any(), \Mockery::any(), \Mockery::on(fn ($tag) => $tag === 'Maps'))
                ->andReturn(['hits' => ['total' => ['value' => 7], 'hits' => []]]);
            $mock->shouldReceive('docsBySystem')
                ->with('S', \Mockery::any(), \Mockery::any(), \Mockery::on(fn ($tag) => $tag === false))
                ->andReturn(['hits' => ['total' => ['value' => 350], 'hits' => []]]);
        });

        $filtered = $this->get('/system/S/art?tag=Maps')->assertOk();
        $filtered->assertSee('Documents <span class="tag-count">(7)</span>', false)
            ->assertSee('Art <span class="tag-count">(1)</span>', false)
            ->assertSee('Music <span class="tag-count">(1)</span>', false);

        $this->get('/system/S/art')->assertOk()
            ->assertSee('Documents <span class="tag-count">(350)</span>', false)
            ->assertSee('Art <span class="tag-count">(2)</span>', false);
    }

    public function test_empty_tag_filter_offers_a_way_back(): void
    {
        $this->login();
        $this->item('S/Maps/a.png', MediaKind::Art, 'S', ['Maps'], 'A');

        $this->get('/system/S/music?tag=Maps')->assertOk()
            ->assertSee('No music tagged', false)
            ->assertSee('Show all music');
    }

    public function test_array_tag_is_ignored_rather_than_erroring(): void
    {
        $this->login();
        $this->item('S/Maps/a.png', MediaKind::Art, 'S', ['Maps'], 'A');

        $this->get('/system/S/art?tag[]=Maps')->assertOk()->assertSee('A');
    }

    public function test_artist_and_album_columns_only_appear_when_tracks_have_them(): void
    {
        $this->login();
        $this->item('S/Ambience/plain.mp3', MediaKind::Music, 'S', ['Ambience'], 'Plain');

        $this->get('/system/S/music')->assertOk()
            ->assertSee('Plain')
            ->assertSee('>Title<', false)
            ->assertSee('>Length<', false)
            ->assertDontSee('>Artist<', false)
            ->assertDontSee('>Album<', false);

        $this->item('S/Ambience/tagged.mp3', MediaKind::Music, 'S', ['Ambience'], 'Tagged', ['artist' => 'Someone']);

        $this->get('/system/S/music')->assertOk()
            ->assertSee('>Artist<', false)
            ->assertDontSee('>Album<', false);
    }

    /** An href attribute as Blade renders it (ampersands escaped). */
    private function href(string $url): string
    {
        return 'href="'.e($url).'"';
    }

    private function tracks(): void
    {
        $this->item('S/A/alpha.mp3', MediaKind::Music, 'S', ['A'], 'Alpha', ['artist' => 'Zed', 'track_number' => 3, 'duration_seconds' => 30]);
        $this->item('S/A/bravo.mp3', MediaKind::Music, 'S', ['A'], 'Bravo', ['artist' => 'Yan', 'track_number' => 2, 'duration_seconds' => 90]);
        $this->item('S/A/charlie.mp3', MediaKind::Music, 'S', ['A'], 'Charlie', ['artist' => 'Xi', 'track_number' => 1, 'duration_seconds' => 60]);
        $this->item('S/A/zulu.mp3', MediaKind::Music, 'S', ['A'], 'Zulu', ['artist' => 'Wu']);
    }

    public function test_music_sorts_by_each_column_and_direction(): void
    {
        $this->login();
        $this->tracks();

        $order = [
            // default is title ascending
            '' => ['Alpha', 'Bravo', 'Charlie', 'Zulu'],
            '?sort=title&dir=desc' => ['Zulu', 'Charlie', 'Bravo', 'Alpha'],
            '?sort=track&dir=asc' => ['Charlie', 'Bravo', 'Alpha', 'Zulu'],
            // artists Wu, Xi, Yan, Zed belong to Zulu, Charlie, Bravo, Alpha
            '?sort=artist&dir=asc' => ['Zulu', 'Charlie', 'Bravo', 'Alpha'],
            '?sort=length&dir=desc' => ['Bravo', 'Charlie', 'Alpha', 'Zulu'],
        ];

        foreach ($order as $query => $titles) {
            $this->get('/system/S/music'.$query)->assertOk()->assertSeeInOrder($titles);
        }
    }

    public function test_tracks_without_a_value_sort_last_in_both_directions(): void
    {
        $this->login();
        $this->tracks();

        $this->get('/system/S/music?sort=track&dir=asc')->assertSeeInOrder(['Charlie', 'Bravo', 'Alpha', 'Zulu']);
        $this->get('/system/S/music?sort=track&dir=desc')->assertSeeInOrder(['Alpha', 'Bravo', 'Charlie', 'Zulu']);
    }

    public function test_unknown_sort_values_fall_back_to_title_order(): void
    {
        $this->login();
        $this->tracks();

        $this->get('/system/S/music?sort=password&dir=sideways')->assertOk()
            ->assertSeeInOrder(['Alpha', 'Bravo', 'Charlie', 'Zulu']);
        $this->get('/system/S/music?sort[]=x&dir[]=y')->assertOk();
    }

    public function test_column_headers_link_to_the_sorted_view_and_toggle_direction(): void
    {
        $this->login();
        $this->tracks();

        // Not sorted by track yet: its header offers ascending.
        $this->get('/system/S/music')->assertOk()
            ->assertSee($this->href(route('system.music', ['system' => 'S', 'sort' => 'track', 'dir' => 'asc'])).' data-section-link', false)
            ->assertSee('>#<', false)
            ->assertSee('aria-sort="ascending"', false);

        // Already ascending: clicking again goes descending, and the header says so.
        $this->get('/system/S/music?sort=track&dir=asc')->assertOk()
            ->assertSee($this->href(route('system.music', ['system' => 'S', 'sort' => 'track', 'dir' => 'desc'])).' data-section-link', false);
        $this->get('/system/S/music?sort=track&dir=desc')->assertOk()->assertSee('aria-sort="descending"', false);
    }

    public function test_sort_order_survives_changing_tag_and_the_track_column_needs_track_numbers(): void
    {
        $this->login();
        $this->tracks();

        $this->get('/system/S/music?sort=length&dir=desc')->assertOk()
            ->assertSee($this->href(route('system.music', ['system' => 'S', 'tag' => 'A', 'sort' => 'length', 'dir' => 'desc'])), false);

        $this->item('S/B/untracked.mp3', MediaKind::Music, 'T', ['B'], 'Untracked');
        $this->get('/system/T/music')->assertOk()->assertSee('Untracked')->assertDontSee('>#<', false);
    }

    /** The paginated item query's ORDER BY clause for a request. */
    private function itemOrderBy(string $url): string
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        foreach (DB::getQueryLog() as $entry) {
            if (str_contains($entry['query'], 'from "media_items"') && str_contains($entry['query'], ' limit ')) {
                preg_match('/order by (.*?) limit/i', $entry['query'], $match);

                return $match[1] ?? '';
            }
        }

        return '';
    }

    public function test_listings_have_a_total_order_so_paging_is_stable(): void
    {
        // Titles come from filenames and often repeat; without a unique last key,
        // MySQL may repeat or skip rows across pages.
        $this->login();
        $this->tracks();
        $this->item('S/Maps/a.png', MediaKind::Art, 'S', ['Maps'], 'A'); // an empty page skips the items query

        foreach (['/system/S/art', '/system/S/music', '/system/S/music?sort=artist', '/system/S/music?sort=length&dir=desc'] as $url) {
            $orderBy = $this->itemOrderBy($url);

            $this->assertNotSame('', $orderBy, $url);
            $this->assertStringEndsWith('"id" asc', trim($orderBy), $url);
        }
    }
}
