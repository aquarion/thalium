<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Libris\LibrisInterface;

class LibrisThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:thumbnails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(LibrisInterface $libris)
    {
        $page = 1;
        $i=0;
        $this->info("Generating thumbnails");

        while($page){
            $docs = $libris->showAll($page);

            $total = $docs['hits']['total']['value'];

            if(count($docs['hits']['hits']) == 0){
                return;
            }

            foreach($docs['hits']['hits'] as $doc){
                $i++;
                $this->info($doc['_source']['path']." $i/$total");
                $libris->getThumbnail($doc);
            }
            $page++;
        }

    }
}
