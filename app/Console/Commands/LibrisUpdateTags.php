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
        $this->line("Updating tags, one sec...");

        $size   = 100;
        $page   = 1;
        $cursor = 0;

        $results = $libris->showAll(1, 0);
        $total   = $results['hits']['total']['value'];
        $pages   = ceil(($results['hits']['total']['value'] / $size));


        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] - %message%');
        $bar->setMessage('Start');


        $bar->start();
        for ($page = 1; $page <= $pages; $page++) {
            $docs = $libris->showAll($page, $size);
            foreach ($docs['hits']['hits'] as $index => $doc) {
                // dd($doc);
                $parser = $libris->getParser($doc['_source']['path']);
                $bar->setMessage($parser->filename);

                if ($parser->tags == $doc['_source']['tags']) {
                    // $this->info('From '. implode(',', $doc['_source']['tags']).' ('.count($parser->tags).') to '.implode(',', $parser->tags).' ('.count($parser->tags).') - No change!');
                } else {
                    // $this->info('     '.$parser->filename.' - from '. implode(',', $doc['_source']['tags']).' ('.count($doc['_source']['tags']).') to '.implode(',', $parser->tags).' ('.count($parser->tags).') - Update!');
                    $body = [
                        'doc' => ['tags'          => $parser->tags],
                    ];
                    $result = $libris->updateDocument($doc['_id'], $body);
                }
                $bar->advance();
            }
        }
        $bar->finish();
    }//end handle()
}//end class
