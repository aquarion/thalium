<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LibrisUpdateTags extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:update_tags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update tags on documents';

    protected $searchAfter = false;

    /**
     * Get the next page of results
     *
     * @return int
     */


    private function nextPage($size=100)
    {
        // if ($this->option('system')) {
        //     $docs = $this->libris->docsBySystem($this->option('system'), $page);
        // } else {
        $docs = $this->libris->fetchAllDocuments(false, $size, $this->searchAfter);
        // }

        return $docs;

    }//end nextPage()


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
    public function handle(LibrisInterface $libris): int
    {
        $this->line("Updating tags, one sec...");

        $this->libris = $libris;

        $size   = 100;
        $cursor = 0;

        $total = $this->libris->countAllDocuments();

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage('Start');

        $bar->start();
        while (true) {
            $docs = $this->nextPage();

            if (count($docs['hits']['hits']) == 0) {
                break;
            }

            foreach ($docs['hits']['hits'] as $index => $doc) {
                // dd($doc);
                $parser = $libris->getParser($doc['_source']['path']);
                $bar->setMessage($parser->filename);

                if ($parser->tags == $doc['_source']['tags']) {
                    // $this->info('From '. implode(',', $doc['_source']['tags']).' ('.count($parser->tags).') to '.implode(',', $parser->tags).' ('.count($parser->tags).') - No change!');
                } else {
                    // $this->info('     '.$parser->filename.' - from '. implode(',', $doc['_source']['tags']).' ('.count($doc['_source']['tags']).') to '.implode(',', $parser->tags).' ('.count($parser->tags).') - Update!');
                    $body   = [
                        'doc' => ['tags' => $parser->tags],
                    ];
                    $result = $libris->updateDocument($doc['_id'], $body);
                }

                $this->searchAfter = $doc['sort'];

                $bar->advance();
            }
        }//end while

        $bar->finish();
        $this->line("Have a great day.");

    }//end handle()


}//end class
