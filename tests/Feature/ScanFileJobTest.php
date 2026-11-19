<?php

namespace Tests\Feature;

use App\Jobs\ScanFile;
use App\Libris\LibrisInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ScanFileJobTest extends TestCase
{
    protected function tearDown(): void
    {
        // The limit is process-wide: don't leak a test's value into the next test.
        set_time_limit(0);
        parent::tearDown();
    }

    /**
     * Stand in for the Redis funnel: either run the job (a slot was free) or its bounce callback.
     */
    private function funnel(bool $acquired): void
    {
        $funnel = Mockery::mock();
        $funnel->shouldReceive('limit')->andReturnSelf();
        $funnel->shouldReceive('then')->andReturnUsing(
            fn (callable $acquiredCallback, callable $bounced) => $acquired ? $acquiredCallback() : $bounced()
        );
        Redis::shouldReceive('funnel')->with('ScanFile')->andReturn($funnel);
    }

    /**
     * A library whose scan arms a time limit (what constructing a PDFBoxService does) and then
     * either succeeds or throws $thenThrows.
     */
    private function libraryThatArmsALimit(?\Throwable $thenThrows = null): MockInterface
    {
        $libris = Mockery::mock(LibrisInterface::class);
        $libris->shouldReceive('scanFile')->once()->andReturnUsing(function () use ($thenThrows) {
            set_time_limit(120);

            if ($thenThrows) {
                throw $thenThrows;
            }

            return 0;
        });

        return $libris;
    }

    /**
     * A queue worker outlives its jobs, so a limit armed by a scan would otherwise fire later,
     * in the worker's own loop.
     */
    public function test_a_time_limit_set_during_a_job_does_not_outlive_it(): void
    {
        $this->funnel(true);

        (new ScanFile('S/a.png'))->handle($this->libraryThatArmsALimit());

        $this->assertSame('0', ini_get('max_execution_time'));
    }

    public function test_a_time_limit_left_by_earlier_work_is_cleared_before_scanning(): void
    {
        $this->funnel(true);
        $seen = null;
        $libris = Mockery::mock(LibrisInterface::class);
        $libris->shouldReceive('scanFile')->once()->andReturnUsing(function () use (&$seen) {
            $seen = ini_get('max_execution_time');

            return 0;
        });
        set_time_limit(120); // left by an earlier job in the same worker

        (new ScanFile('S/a.png'))->handle($libris);

        $this->assertSame('0', $seen);
    }

    public function test_the_time_limit_is_cleared_when_the_scan_fails_and_the_job_is_failed(): void
    {
        $this->funnel(true);
        $exception = new \RuntimeException('parse failed');
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('fail')->once()->with($exception);
        $job = new ScanFile('S/a.pdf');
        $job->setJob($queueJob);

        $job->handle($this->libraryThatArmsALimit($exception));

        $this->assertSame('0', ini_get('max_execution_time'));
    }

    /**
     * Only \Exception is caught inside the job; anything else escapes handle(), and only the
     * `finally` clears the limit then.
     */
    public function test_the_time_limit_is_cleared_when_something_other_than_an_exception_escapes(): void
    {
        $this->funnel(true);
        $thrown = null;

        try {
            (new ScanFile('S/a.png'))->handle($this->libraryThatArmsALimit(new \TypeError('boom')));
        } catch (\TypeError $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'the TypeError should escape handle()');
        $this->assertSame('0', ini_get('max_execution_time'));
    }

    public function test_a_bounced_job_is_released_and_clears_the_time_limit(): void
    {
        $this->funnel(false);
        $libris = Mockery::mock(LibrisInterface::class);
        $libris->shouldNotReceive('scanFile');
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('release')->once()->with(Mockery::on(fn ($delay) => $delay >= 60 && $delay <= 80));
        $job = new ScanFile('S/a.png');
        $job->setJob($queueJob);
        set_time_limit(120);

        $job->handle($libris);

        $this->assertSame('0', ini_get('max_execution_time'));
    }
}
