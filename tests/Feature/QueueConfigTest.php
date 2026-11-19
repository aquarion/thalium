<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueConfigTest extends TestCase
{
    /**
     * A job still running when the connection's retry_after passes is handed to a second
     * worker, so it has to outlast the longest job Horizon will allow.
     */
    public function test_retry_after_outlasts_every_horizon_job_timeout(): void
    {
        $retryAfter = config('queue.connections.redis.retry_after');

        foreach (config('horizon.environments') as $environment => $supervisors) {
            foreach ($supervisors as $name => $supervisor) {
                $this->assertGreaterThan(
                    $supervisor['timeout'],
                    $retryAfter,
                    "retry_after must exceed the [$environment/$name] timeout"
                );
            }
        }
    }
}
