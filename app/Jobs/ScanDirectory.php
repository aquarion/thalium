<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Jobs\ScanPDF;
use App\Libris\LibrisInterface;

class ScanDirectory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $system;
    protected $tags;
    protected $directory;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($system, $tags, $directory)
    {
        $this->system = $system;
        $this->tags = $tags;
        $this->directory = $directory;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $system = $this->system;
        $tags = $this->tags;
        $directory = $this->directory;
        Log::debug("[ScanDir] Sys: $system, Tags: ".implode(',', $tags).", Dir: $directory");

        $files = Storage::disk('libris')->files($directory);
        $subdirs = Storage::disk('libris')->directories($directory);
        

        foreach ($files as $filename) {
            $tags = explode('/', $filename);
            // Execute scan job.
            Log::debug("[ScanDir] New File Scan Job: $filename");
            ScanPDF::dispatch($system, $tags, $filename);
            //$return[$system]['files'][$filename] = $this->addDocument($system, $tags, $filename);
        }


        foreach ($subdirs as $directory) {
            $tags = explode('/', $directory);
            $system = array_unshift($tags);

            Log::debug("[ScanDir] New Dir Scan Job: $directory");
            // Execute scan job.
            //$return[$system]['files'][$filename] = $this->addDocument($system, $tags, $filename);
            ScanDirectory::dispatch($system, $tags, $directory);
        }
    }

}
