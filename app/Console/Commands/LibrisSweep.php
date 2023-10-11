<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libris\LibrisInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LibrisSweep extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:sweep';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up any Page objects that don\'t have parent Doc objects';

    protected $searchAfter = false;

    protected $missingCache = [];

    protected $presentCache = [];


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
        $this->libris = $libris;

        $this->purgeDeletedPages();

    }//end handle()


    private function purgeDeletedPages($docId=false)
    {
        $total = $this->libris->countAllPages($docId);

        $bar = $this->output->createProgressBar($total);
        // $bar->setFormat(' %current%/%max% [%bar%] - %message%');

        $bar->setMessage('Finding orphaned pages');
        $bar->start();

        $deleted  = 0;
        $filename = false;

        $size        = 100;
        $searchAfter = false;
        $this->libris->openPointInTime();

        while (true) {
            $pages = $this->libris->fetchAllPages($docId, $size, $searchAfter);
            if (count($pages['hits']['hits']) == 0) {
                break;
            }

            foreach ($pages['hits']['hits'] as $index => $page) {
                $pageId = $page['_id'];
                // if($filename != $page['_source']['path']) {
                //     $bar->setMessage("[" . $deleted . "] " . $filename);
                // }
                $filename = $page['_source']['path'];
                if ($this->isMissing($filename)) {
                    $this->libris->deletePage($pageId);
                    $bar->setMessage(" Deleted ".$pageId);
                    $deleted++;
                }

                $bar->advance();

                $searchAfter = $page['sort'];
            }
        }//end while

        $bar->finish();
        $this->line(" - Complete");

    }//end purgeDeletedPages()


    protected function isMissing($docId)
    {
        if (array_key_exists($docId, $this->presentCache)) {
            return false;
        } else if (array_key_exists($docId, $this->missingCache)) {
            return true;
        }

        if (Storage::disk('libris')->missing($docId)) {
            array_push($this->missingCache, $docId);
            return true;
        } else {
            array_push($this->presentCache, $docId);
            return false;
        }

        throw new \Exception("Shouldn't have got here");

    }//end isMissing()


}//end class
