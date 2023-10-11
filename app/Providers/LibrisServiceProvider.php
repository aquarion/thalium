<?php

namespace App\Providers;

use App\Libris\LibrisService;
use App\Libris\LibrisInterface;

use Illuminate\Support\ServiceProvider;

class LibrisServiceProvider extends ServiceProvider
{


    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(
            LibrisInterface::class,
            function () {
                return new LibrisService();
            }
        );

    }//end register()


    /**
     * Bootstrap services.
     */
    public function boot(): void
    {

    }//end boot()


}//end class
