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

    protected $searchAfter = false;

    /**
     * Get the next page of results
     *
     * @return int
     */


    private function nextPageOfDocs($size=100)
    {
        // if ($this->option('system')) {
        //     $docs = $this->libris->docsBySystem($this->option('system'), $page);
        // } else {
        $docs = $this->libris->fetchAllDocuments(false, $size, $this->searchAfter);
        // }

        return $docs;

    }//end nextPageOfDocs()


    private function nextPageOfPages($size=100)
    {
        // if ($this->option('system')) {
        //     $docs = $this->libris->docsBySystem($this->option('system'), $page);
        // } else {
        $docs = $this->libris->fetchAllPages(false, $size, $this->searchAfter);
        // }

        return $docs;

    }//end nextPageOfPages()


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
        $this->libris = $libris;

        $this->purgeDeletedDocuments();
        $this->purgeDeletedPages();

    }//end handle()


    private function purgeDeletedDocuments()
    {
        $this->searchAfter = false;
        $size = 100;

        $total = $this->libris->countAllDocuments();

        $pages = ceil(($total / $size));

        $deletionList = [];

        $this->line("Scanning Library:");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage('Start');
        $bar->start();

        while (true) {
            $docs = $this->nextPageOfDocs($size, $this->searchAfter);
            if (count($docs['hits']['hits']) == 0) {
                break;
            }

            foreach ($docs['hits']['hits'] as $index => $doc) {
                $filename = $doc['_source']['path'];
                $bar->setMessage($filename);
                if (Storage::disk('libris')->missing($filename)) {
                    $deletionList[] = $doc['_id'];
                }

                $this->searchAfter = $doc['sort'];

                $bar->advance();
            }
        }

        $bar->finish();
        $this->line(" ... done");

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

        $this->line(" - Complete");

    }//end purgeDeletedDocuments()


    private function purgeDeletedPages()
    {
        $total = $this->libris->countAllPages();

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage('Finding orphaned pages');
        $bar->start();

        $size = 100;
        $this->searchAfter = false;
        $this->libris->openPointInTime();
        while (true) {
            $pages = $this->nextPageOfPages($size, $this->searchAfter);
            if (count($pages['hits']['hits']) == 0) {
                break;
            }

            foreach ($pages['hits']['hits'] as $index => $page) {
                $docId    = $page['_id'];
                $filename = $page['_source']['path'];
                if (Storage::disk('libris')->missing($filename)) {
                    $this->libris->deleteDocument($docId);
                    $bar->setMessage(" Deleted ".$docId);
                }

                $bar->advance();

                $this->searchAfter = $page['sort'];
            }
        }

        $bar->finish();
        $this->line(" - Complete");

    }//end purgeDeletedPages()


}//end class
