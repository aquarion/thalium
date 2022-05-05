<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LibrisPurge extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:purge_deleted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge deleted files from index';


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
     *
     * @return int
     */
    public function handle(LibrisInterface $libris)
    {


        $size   = 100;
        $page   = 1;
        $cursor = 0;

        $results = $libris->showAll(1, 0);
        $total = $results['hits']['total']['value'];

        $pages   = ceil(($total / $size));

        $deletionList = [];

        $this->line("Scanning Library:");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage('Start');
        $bar->start();

        for ($page = 1; $page <= $pages; $page++) {
            $docs = $libris->showAll($page, $size);
            foreach ($docs['hits']['hits'] as $index => $doc) {
                $filename = $doc['_source']['path'];
                $bar->setMessage($filename);
                if (Storage::disk('libris')->missing($filename)) {
                    $deletionList[] = $doc['_id'];
                }
                $bar->advance();
            }
        }
        $bar->finish();

        if (count($deletionList)) {
            $this->line("Deleting Records:");
            foreach ($deletionList as $line) {
                $this->line(" * ".$line);
            }
        } else {
            $this->line("Nothing to delete");
            return;
        }

        $bar = $this->output->createProgressBar(count($deletionList));
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage('Start');
        $bar->start();
        foreach ($deletionList as $docId) {
            $bar->setMessage($docId);
            $libris->deleteDocument($docId);
            $bar->advance();
        }

        $bar->finish();

        $this->line("Have a great day.");

    }//end handle()


}//end class
