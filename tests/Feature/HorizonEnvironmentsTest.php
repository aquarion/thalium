<?php

namespace Tests\Feature;

use Tests\TestCase;

class HorizonEnvironmentsTest extends TestCase
{
    /**
     * Horizon starts "successfully" with no workers for an environment it has no supervisors
     * for. Naming the set makes adding or renaming an environment a deliberate choice.
     */
    public function test_the_known_environments_are_the_ones_configured(): void
    {
        $environments = array_keys(config('horizon.environments'));
        sort($environments);

        $this->assertSame(['local', 'production', 'staging'], $environments);
    }

    public function test_every_configured_environment_runs_workers_on_the_default_queue(): void
    {
        foreach (config('horizon.environments') as $environment => $supervisors) {
            $this->assertNotEmpty($supervisors, "No Horizon supervisors for [$environment]");

            $queues = array_merge(...array_column($supervisors, 'queue'));

            $this->assertContains('default', $queues, "[$environment] does not work the default queue");
            $this->assertGreaterThan(
                0,
                array_sum(array_column($supervisors, 'processes')),
                "[$environment] has no worker processes"
            );
        }
    }
}
