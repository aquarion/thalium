<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

use App\Libris\LibrisInterface;

class ScanPDF implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $system;
    protected $tags;
    protected $filename;

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
    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Job as string
     *
     * @return void
     */
    public function __toString()
    {
        return sprintf("ScanPDF <%s>", $this->filename);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(LibrisInterface $libris)
    {
        $this->libris = $libris;

        Log::info("[Scanfile] Hello ".$this->filename);

        Redis::funnel('ScanPDF')->limit(5)->then(function () {
            Log::debug("[Scanfile] Got lock for ".$this->filename." = ".md5($this->filename));
            try {
                $returnValue = $this->libris->addDocument($this->filename);
                Log::info("[Scanfile] Finished ".$this->filename);
            } catch (Exception $e){
                Log::info("[Scanfile] Caught Exception");
                $this->fail($e);
                return;
            }
            if(!$returnValue){
                Log::error("[Scanfile] Bad return value ($returnValue) from PDFBox");
                $this->fail();
            }
            return $returnValue;
        }, function () {
            $release = 60+rand(0,20);
            // Could not obtain lock...
            Log::debug("[Scanfile] ".$this->filename." bounced ".$release);
            return $this->release($release);
        });

        // Log::debug("[ScanFile] Bye ".$this->filename);
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        // Log::debug("[ScanFile] Add ".(60*60*24)." secs to ".$this->filename);
        return now()->addSeconds(60*60*24);
    }

    public function uniqueId()
    {
        return md5($this->filename);
    }
}
