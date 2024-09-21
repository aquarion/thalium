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
     */
    public function handle(LibrisInterface $libris): void
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

        $this->libris->sweepPages($docId, $bar);

        $bar->finish();
        $this->line(" - Complete");

    }//end purgeDeletedPages()




}//end class
