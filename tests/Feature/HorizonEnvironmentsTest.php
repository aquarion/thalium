<?php

namespace Tests\Feature;

use Tests\TestCase;

class HorizonEnvironmentsTest extends TestCase
{
    /**
     * Horizon only provisions workers for an environment it has supervisors for, and it
     * starts "successfully" with none: staging ran an idle Horizon for that reason.
     */
    public function test_every_deployed_environment_has_a_supervisor_on_the_default_queue(): void
    {
        foreach (['production', 'staging', 'local'] as $environment) {
            $supervisors = config("horizon.environments.$environment");

            $this->assertNotEmpty($supervisors, "No Horizon supervisors for [$environment]");
            $this->assertContains('default', $supervisors['supervisor-1']['queue'], $environment);
            $this->assertGreaterThan(0, $supervisors['supervisor-1']['processes'], $environment);
        }
    }

    public function test_staging_is_no_bigger_than_production(): void
    {
        // Staging shares production's Elasticsearch index.
        $this->assertLessThanOrEqual(
            config('horizon.environments.production.supervisor-1.processes'),
            config('horizon.environments.staging.supervisor-1.processes')
        );
    }
}
