<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Libris\LibrisInterface;

class LibrisCreate extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libris:index:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create/Update Libris index and pipelines';


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
     * @return mixed
     */
    public function handle(LibrisInterface $libris)
    {
           $libris->updateIndex();
           $libris->updatePipeline();
           return "Done";

    }//end handle()


}//end class
