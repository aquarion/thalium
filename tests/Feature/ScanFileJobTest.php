<?php

namespace Tests\Feature;

use App\Jobs\ScanFile;
use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class ScanFileJobTest extends TestCase
{
    protected function tearDown(): void
    {
        set_time_limit(0);
        parent::tearDown();
    }

    private function runThroughFunnel(): void
    {
        $funnel = Mockery::mock();
        $funnel->shouldReceive('limit')->andReturnSelf();
        $funnel->shouldReceive('then')->andReturnUsing(fn (callable $acquired) => $acquired());
        Redis::shouldReceive('funnel')->with('ScanFile')->andReturn($funnel);
    }

    /**
     * PDFBoxService caps the whole process at 120 seconds, and a queue worker outlives the
     * job, so the cap would otherwise fire later in the worker's own loop.
     */
    public function test_a_time_limit_left_before_or_set_during_a_job_does_not_outlive_it(): void
    {
        $this->runThroughFunnel();
        $libris = Mockery::mock(LibrisInterface::class);
        $libris->shouldReceive('scanFile')->once()->andReturnUsing(function () {
            set_time_limit(120); // what constructing a PDFBoxService does

            return 0;
        });
        set_time_limit(120); // left by an earlier PDF job in the same worker

        (new ScanFile('S/a.png'))->handle($libris);

        $this->assertSame('0', ini_get('max_execution_time'));
    }

    public function test_the_job_clears_the_time_limit_before_scanning(): void
    {
        $this->runThroughFunnel();
        $seen = null;
        $libris = Mockery::mock(LibrisInterface::class);
        $libris->shouldReceive('scanFile')->once()->andReturnUsing(function () use (&$seen) {
            $seen = ini_get('max_execution_time');

            return 0;
        });
        set_time_limit(120);

        (new ScanFile('S/a.png'))->handle($libris);

        $this->assertSame('0', $seen);
    }
}
