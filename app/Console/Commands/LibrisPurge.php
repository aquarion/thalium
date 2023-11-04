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

    protected $missingCache = [];

    /**
     * Get the next page of results
     */


    private function nextPageOfDocs($size=100): array
    {
        // if ($this->option('system')) {
        //     $docs = $this->libris->docsBySystem($this->option('system'), $page);
        // } else {
        $docs = $this->libris->fetchAllDocuments(false, $size, $this->searchAfter);
        // }

        return $docs;

    }//end nextPageOfDocs()


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

        $this->purgeDeletedDocuments();

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
            $bar->setMessage($docId." - Delete Doc");
            $this->libris->deleteDocument($docId);
            $bar->advance();
        }

        $bar->finish();

        $this->line(" - Complete");

    }//end purgeDeletedDocuments()


}//end class
