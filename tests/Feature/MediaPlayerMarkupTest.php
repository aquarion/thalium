<?php

namespace Tests\Feature;

use App\Libris\LibrisInterface;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MediaPlayerMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_markup_only_for_authenticated_users(): void
    {
        $this->withoutVite();
        $this->mock(LibrisInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('docsBySystem')->andReturn(['hits' => ['total' => ['value' => 0], 'hits' => []]]);
        });

        $this->get('/login')->assertOk()->assertDontSee('id="media-player"', false);

        $this->actingAs(UserFactory::new()->create());

        $this->get('/system/S/music')
            ->assertOk()
            ->assertSee('id="media-player"', false)
            ->assertSee('id="media-audio"', false)
            ->assertSee('data-section-link', false);
    }
}
