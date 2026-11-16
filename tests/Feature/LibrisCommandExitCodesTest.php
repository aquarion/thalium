<?php

namespace Tests\Feature;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LibrisCommandExitCodesTest extends TestCase
{
    public function test_index_delete_reports_success_only_after_deleting(): void
    {
        $this->mock(LibrisInterface::class)->shouldReceive('deleteIndex')->once();

        $this->artisan('libris:index:delete')
            ->expectsOutputToContain('Index deleted.')
            ->assertSuccessful();
    }

    public function test_index_delete_does_not_claim_success_when_the_delete_fails(): void
    {
        $this->mock(LibrisInterface::class)->shouldReceive('deleteIndex')->andThrow(new RuntimeException('denied'));

        try {
            Artisan::call('libris:index:delete');
            $this->fail('The failed delete should have thrown');
        } catch (RuntimeException) {
            $this->assertStringNotContainsString('Index deleted.', Artisan::output());
        }
    }

    public function test_add_fails_when_the_file_is_not_on_disk(): void
    {
        Storage::fake('libris');
        $this->mock(LibrisInterface::class);

        $this->artisan('libris:add', ['filename' => 'Nope/missing.pdf'])
            ->expectsOutputToContain('not found on disk')
            ->assertFailed();
    }
}
