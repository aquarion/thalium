<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Console\Commands\LibrisPurge;
use App\Console\Commands\LibrisRebuild;

class Kernel extends ConsoleKernel
{

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];


    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('libris:update')->daily()->onOneServer();
        $schedule->command('libris:purge_deleted')->daily()->onOneServer();
        $schedule->command('libris:sweep')->weekly()->onOneServer();

    }//end schedule()


    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        include base_path('routes/console.php');

    }//end commands()


}//end class
