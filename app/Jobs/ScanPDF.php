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
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($system, $tags, $filename)
    {
        $this->system = $system;
        $this->tags = $tags;
        $this->filename = $filename;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(LibrisInterface $libris)
    {
        $this->libris = $libris;

        Log::debug("[ScanFile] Hello ".$this->filename);

        $mimeType = Storage::disk('libris')->mimeType($this->filename);

        if($mimeType !== "application/pdf"){
            Log::debug("[ScanFile] Ignoring $mimeType file ".$this->filename);
        }
        
        if($doc = $this->libris->fetchDocument($this->filename)){
            Log::Debug("[ScanFile] Already got");
            Log::Debug($doc);
            return;
        }

        Redis::funnel('ScanPDF')->limit(1)->then(function () {
            Log::debug("[ScanFile] Got lock for ".$this->filename);
            $this->libris->addDocument($this->system, $this->tags, $this->filename);
        }, function () {
            // Could not obtain lock...

            return $this->release(10);
        });
        Log::debug("[ScanFile] Finished ".$this->filename);
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        Log::debug("[ScanFile] Add 60 secs to ".$this->filename);
        return now()->addSeconds(60*60*24);
    }
}
