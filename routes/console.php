<?php

// use Illuminate\Foundation\Inspiring;
// use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();



// $schedule->command('inspire')->hourly();
Schedule::command('libris:update')->daily()->onOneServer()->withoutOverlapping();
Schedule::command('libris:purge_deleted')->daily()->onOneServer();
Schedule::command('libris:sweep')->weekly()->onOneServer();
