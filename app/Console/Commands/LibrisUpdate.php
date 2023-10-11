<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Libris\LibrisInterface;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Jobs\ScanDirectory;
use App\Jobs\ScanFile;

class LibrisUpdate extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Libris index and all document data';


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
    public function handle(LibrisInterface $libris): void
    {

        $this->line("Kicking off a reindex for ".Storage::disk('libris')->path("."));

        $libris->updatePipeline();
        $libris->updateIndex();

        $dirCount  = 0;
        $fileCount = 0;

        $systems = Storage::disk('libris')->directories('.');
        $files   = Storage::disk('libris')->files('.');

        $this->line("Directories:");
        foreach ($systems as $system) {
            ScanDirectory::dispatch($system) && $dirCount++;
            $this->line(" * ".$system);
        }

        if ($files) {
            $this->line("Files:");
            foreach ($files as $filename) {
                $libris->dispatchIndexFile($filename) && $fileCount++;
                $this->line(" * ".$system);
            }
        }

        $this->line("Scanning $dirCount directories & $fileCount files");

    }//end handle()


}//end class
