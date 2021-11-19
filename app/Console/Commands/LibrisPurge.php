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
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(LibrisInterface $libris)
    {
        $this->line("Checking library for deleted files, one sec...");

        $files = $libris->purgeDeletedFiles();

        if($files){
            $this->line("Deleted Files:");
            foreach ($files as $line){
                $this->line(" * ".$line);
            }
        } else {
            $this->line("Nothing to delete");
        }
    }
}
