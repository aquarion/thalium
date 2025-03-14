<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


use App\Jobs\ScanFile;

class LibrisAdd extends Command implements PromptsForMissingInput
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:add {filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index a single file';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }//end __construct()


    /**
     * Execute the console command.
     */
    public function handle(LibrisInterface $libris): int
    {
        $filename = $this->argument('filename');

        if (Storage::disk('libris')->directoryExists($filename)) {
            $this->info("Scanning directory $filename");
            $libris->dispatchIndexDir($filename);
        } else if (Storage::disk('libris')->fileExists($filename)) {
            $this->info("Scanning file $filename");
            $libris->addDocument($filename, $this);
        } else {
            $this->error(Storage::disk('libris')->path($filename).' not found on disk');
        }

        return 0;

    }//end handle()


}//end class
