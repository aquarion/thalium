<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    'providers' => ServiceProvider::defaultProviders()->merge([
        Phattarachai\LaravelMobileDetect\AgentServiceProvider::class, // https://github.com/phattarachai/laravel-mobile-detect

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\RouteServiceProvider::class,


        App\Providers\LibrisServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        'Agent' => Phattarachai\LaravelMobileDetect\Facades\Agent::class, //https://github.com/phattarachai/laravel-mobile-detect
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
