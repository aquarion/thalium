<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Elasticsearch;


use App\Jobs\ScanPDF;

class LibrisRemove extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:remove {filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a single file';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }//end __construct()


    public function debug($out)
    {
        $this->line($out);
    }//end debug()


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(LibrisInterface $libris)
    {
        $filename = $this->argument('filename');
        try {
            $result = $libris->fetchDocument($filename);
            //dump($result);
            $this->line("Found $filename");
            $result = $libris->deleteDocument($filename);
            $this->line("Removed $filename");
        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            $this->error("$filename not found in index");
            return 1;
        }

        return 0;
    }//end handle()
}//end class
