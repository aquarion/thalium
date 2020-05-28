<?php

namespace App\Providers;
 
use App\Libris\LibrisService;
use App\Libris\LibrisInterface;

use Illuminate\Support\ServiceProvider;

class LibrisServiceProvider extends ServiceProvider
{

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(LibrisInterface::class, function() {
            return new LibrisService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
