<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Jobs\ScanFile;
use App\Libris\LibrisInterface;

class ScanDirectory implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $filename;


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
        return sprintf("ScanDir <%s>", $this->filename);

    }//end __toString()


    /**
     * Execute the job.
     */
    public function handle(LibrisInterface $libris): void
    {
        $filename = $this->filename;
        Log::debug("[ScanDir] $filename");

        $files   = Storage::disk('libris')->files($filename);
        $subdirs = Storage::disk('libris')->directories($filename);

        foreach ($files as $file) {
            Log::debug("[ScanDir] New File Scan Job: $file");
            $libris->dispatchIndexFile($file);
        }

        foreach ($subdirs as $directory) {
            Log::debug("[ScanDir] New Dir Scan Job: $directory");
            $libris->dispatchIndexDir($directory);
        }

    }//end handle()


}//end class
