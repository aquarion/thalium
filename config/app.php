<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [


    'aliases' => Facade::defaultAliases()->merge([
        'Agent' => Phattarachai\LaravelMobileDetect\Facades\Agent::class, //https://github.com/phattarachai/laravel-mobile-detect
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
