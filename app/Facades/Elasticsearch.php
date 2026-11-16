<?php

namespace App\Facades;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin Client
 */
class Elasticsearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
