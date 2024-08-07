<?php

namespace App\Jobs;

use DateTime;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

use App\Libris\LibrisInterface;

use App\Exceptions;

class ScanFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $system;

    protected $tags;

    protected $filename;

    protected $libris;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var integer
     */
    public $uniqueFor = 3600;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filename)
    {
        $this->filename = $filename;

    }//end __construct()


    /**
     * Job as string
     */
    public function __toString(): string
    {
        return sprintf("ScanFile <%s>", $this->filename);

    }//end __toString()


    /**
     * Execute the job.
     */
    public function handle(LibrisInterface $libris): void
    {
        $this->libris = $libris;

        Log::info("[Scanfile] Hello ".$this->filename);

        Redis::funnel('ScanFile')->limit(5)->then(
            function () {
                try {
                    $this->libris->scanFile($this->filename);
                } catch (Exception $e) {
                    Log::info("[Scanfile] Caught Exception");
                    $this->fail($e);
                    return;
                }
            },
            function () {
                $release = (60 + rand(0, 20));
                // Could not obtain lock...
                Log::debug("[Scanfile] ".$this->filename." bounced ".$release);
                return $this->release($release);
            }
        );

        // Log::debug("[ScanFile] Bye ".$this->filename);

    }//end handle()


    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        // Log::debug("[ScanFile] Add ".(60*60*24)." secs to ".$this->filename);
        return now()->addSeconds(60 * 60 * 24)->toDateTime();

    }//end retryUntil()


    public function uniqueId()
    {
        return md5($this->filename);

    }//end uniqueId()


}//end class
