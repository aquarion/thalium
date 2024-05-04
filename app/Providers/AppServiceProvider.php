<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\View;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{


    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';


    /**
     * Register any application services.
     */
    public function register(): void
    {

    }//end register()


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        View::share("adobe_client_id", env('ADOBE_CLIENT_ID', false));

        // $this->bootBroadcast();
        // $this->bootEvent();

    }//end boot()


    public function bootBroadcast(): void
    {
        Broadcast::routes();

        include base_path('routes/channels.php');

    }//end bootBroadcast()


    public function bootEvent(): void
    {
        parent::boot();

    }//end bootEvent()


}//end class
