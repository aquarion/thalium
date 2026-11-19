<?php

namespace Tests\Unit;

use App\Jobs\ScanFile;
use PHPUnit\Framework\TestCase;

class ScanFileJobTest extends TestCase
{
    /**
     * A job serialised before the `source` property existed is unserialised without it,
     * so it must default to the Systems library rather than being uninitialised.
     */
    public function test_a_job_queued_before_the_source_existed_still_has_a_library_source(): void
    {
        $job = (new \ReflectionClass(ScanFile::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($job, 'filename'))->setValue($job, 'S/a.pdf');

        $this->assertSame(md5('S/a.pdf'), $job->uniqueId());
    }
}
