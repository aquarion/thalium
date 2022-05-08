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
    protected $signature = 'libris:thumbnails {--system=} {--regen}}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate all thumbnails';

    /**
     * Pagination data.
     *
     * @var array
     */
    protected $searchAfter = false;


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
     * Get the next page of results
     *
     * @return int
     */


    private function nextPage($size=100)
    {
        if ($this->option('system')) {
            $docs = $this->libris->AllBySystem($this->option('system'), $page);
        } else {
            $docs = $this->libris->showAll(false, $size, $this->searchAfter);
        }

        return $docs;

    }//end nextPage()


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(LibrisInterface $libris)
    {
        $this->libris = $libris;

        $size   = 100;
        $cursor = 0;

        $results = $this->libris->showAll(false, 0);

        $total = $results['hits']['total']['value'];

        $this->line("Generating thumbnails");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage('Start');
        $bar->start();

        if ($this->option("regen")) {
            $regen = true;
        } else {
            $regen = false;
        }

        while (true) {
            $docs = $this->nextPage();

            $total = $docs['hits']['total']['value'];

            if (count($docs['hits']['hits']) == 0) {
                break;
            }

            foreach ($docs['hits']['hits'] as $doc) {
                $bar->setMessage($doc['_source']['path']);
                $libris->getThumbnail($doc, $regen);
                // true means force regen
                $bar->advance();
            }

            $this->searchAfter = $doc['sort'];
            // dd($this->searchAfter);
        }//end while

        $bar->finish();

        $this->line(".");
        $this->line("Have a great day.");

    }//end handle()


}//end class
