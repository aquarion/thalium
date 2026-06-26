<?php

namespace App\Exceptions;

use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        if ($exception instanceof NoNodesAvailableException) {
            $host = config('elasticsearch.connections.default.hosts.0.host', 'unknown');
            $port = config('elasticsearch.connections.default.hosts.0.port', 9200);
            $scheme = config('elasticsearch.connections.default.hosts.0.scheme', 'null(default http)');
            $user = config('elasticsearch.connections.default.hosts.0.user') ? 'SET' : 'UNSET';
            $pass = config('elasticsearch.connections.default.hosts.0.pass') ? 'SET' : 'UNSET';
            $ssl = config('elasticsearch.connections.default.sslVerification', 'null(system CA)');

            Log::error("[ElasticSearch] Cannot connect — scheme={$scheme} host={$host}:{$port} user={$user} pass={$pass} sslVerification={$ssl} — see storage/logs/elasticsearch.log for transport details");
        }

        parent::report($exception);

    }// end report()

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @return Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        return parent::render($request, $exception);

    }// end render()

}// end class
