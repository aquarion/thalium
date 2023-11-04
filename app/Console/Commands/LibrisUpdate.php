<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Libris\LibrisInterface;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

use App\Jobs\ScanDirectory;
use App\Jobs\ScanFile;

class LibrisUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:update {--foreground}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Libris index and all document data';

    /**
     * The service object
     *
     * @var string
     */
    protected $libris;

    /**
     * The progress bar object
     *
     * @var string
     */
    protected $bar;

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
        $this->libris = $libris;

        $this->line("Kicking off a reindex for " . Storage::disk('libris')->path("."));

        $this->libris->updatePipeline();
        $this->libris->updateIndex();

        $dirCount  = 0;
        $fileCount = 0;

        if($this->option('foreground')) {
            $this->foregroundUpdate();
        } else {
            $this->backgroundUpdate();
        }


    }//end handle()

    public function backgroundUpdate()
    {
        $systems = Storage::disk('libris')->directories('.');
        $files   = Storage::disk('libris')->files('.');

        $this->line("Directories:");
        foreach ($systems as $system) {
            ScanDirectory::dispatch($system) && $dirCount++;
            $this->line(" * " . $system);
        }

        if ($files) {
            $this->line("Files:");
            foreach ($files as $filename) {
                $this->libris->dispatchIndexFile($filename) && $fileCount++;
                $this->line(" * " . $system);
            }
        }

        $this->line("Scanning $dirCount directories & $fileCount files");

    }

    public function foregroundUpdate()
    {
        $this->scanDirectory(".");
    }

    public function scanDirectory($dir)
    {

        $files   = Storage::disk('libris')->files($dir);
        $subdirs = Storage::disk('libris')->directories($dir);

        $section = $this->output->section($dir);


        $bar = new ProgressBar($section);
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage($dir);
        $bar->start(count($files) + count($subdirs));

        foreach ($files as $file) {
            Log::debug("[ScanDir] New File Scan Job: $file");
            $this->libris->scanFile($file);
            $bar->advance();
        }
        

        foreach ($subdirs as $directory) {
            Log::debug("[ScanDir] New Dir Scan Job: $directory");
            $this->scanDirectory($directory);
            $bar->advance();
        }
        
        $bar->finish();
        $section->clear();
    }


}//end class
