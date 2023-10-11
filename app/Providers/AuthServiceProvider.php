<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{

    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [];
    // eg. 'App\Model' => 'App\Policies\ModelPolicy',


    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
    }//end boot()


}//end class
