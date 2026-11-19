<?php

namespace App\Jobs;

use App\Libris\LibrisInterface;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ScanFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $system;

    protected $tags;

    protected $filename;

    /** `library` or `maps`: which disk the filename is relative to. */
    protected string $source = 'library';

    protected $libris;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filename, string $source = 'library')
    {
        $this->filename = $filename;
        $this->source = $source;

    }// end __construct()

    /**
     * Job as string
     */
    public function __toString(): string
    {
        return sprintf('ScanFile <%s>', $this->filename);

    }// end __toString()

    /**
     * Execute the job.
     */
    public function handle(LibrisInterface $libris): void
    {
        $this->libris = $libris;

        Log::info('[Scanfile] Hello '.$this->filename);

        // A queue worker outlives its jobs, and PHP's time limit outlives the code that set it:
        // PDFBoxService (so ParsePdfMapService too) arms 120s of execution time and
        // ElasticSearch::indexPages() 30s. Left armed, the limit fires in whatever the worker
        // runs next, such as its idle sleep. So clear it after every scan, and before the first
        // in case other code in this worker left one. Horizon's job timeout supervises the job.
        // (PDFBoxService's 2G memory_limit leaks the same way and is not reset here.)
        set_time_limit(0);

        try {
            Redis::funnel('ScanFile')->limit(5)->then(
                function () {
                    try {
                        $this->libris->scanFile($this->filename, $this->source);
                    } catch (\Exception $e) {
                        Log::info('[Scanfile] Caught Exception');
                        $this->fail($e);

                        return;
                    }
                },
                function () {
                    $release = (60 + rand(0, 20));
                    // Could not obtain lock...
                    Log::debug('[Scanfile] '.$this->filename.' bounced '.$release);

                    return $this->release($release);
                }
            );
        } finally {
            set_time_limit(0);
        }

        // Log::debug("[ScanFile] Bye ".$this->filename);

    }// end handle()

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        // Log::debug("[ScanFile] Add ".(60*60*24)." secs to ".$this->filename);
        return now()->addSeconds(60 * 60 * 24)->toDateTime();

    }// end retryUntil()

    public function uniqueId()
    {
        return md5($this->source === 'library' ? $this->filename : $this->source.':'.$this->filename);

    }// end uniqueId()

}// end class
