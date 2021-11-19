<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


use App\Jobs\ScanPDF;

class LibrisAdd extends Command
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
    }

    public function debug($out){
        $this->line($out);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(LibrisInterface $libris)
    {
        $filename = $this->argument('filename');
        $exists = Storage::disk('libris')->exists($filename);
        if($exists){
            if(Storage::disk('libris')->getMetadata($filename)['type'] === 'dir'){
                $this->info("Scanning directory $filename");
                $libris->indexDirectory($filename);
            } else {
                $this->info("Scanning file $filename");
                $libris->addDocument($filename, $this);
            }
        } else {
            $this->error($filename.' not found in Libris');
        }
        return 0;
    }
}

