<?php

namespace App\Providers;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

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
        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']['elasticsearch.connections.'.$app['config']['elasticsearch.defaultConnection']];
            $host = $config['hosts'][0];

            $url = ($host['scheme'] ?: 'http').'://'.$host['host'];
            if ($host['port']) {
                $url .= ':'.$host['port'];
            }

            $builder = ClientBuilder::create()->setHosts([$url]);

            if ($host['user']) {
                $builder->setBasicAuthentication($host['user'], (string) $host['pass']);
            }

            if ($config['sslVerification']) {
                $builder->setCABundle($config['sslVerification']);
            }

            $logger = new Logger('elasticsearch');
            $logger->pushHandler(new StreamHandler($config['logPath'], $config['logLevel']));

            return $builder->setLogger($logger)->build();
        });
    } // end register()

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        View::share('adobe_client_id', env('ADOBE_CLIENT_ID', false));
        View::share('app_env', env('APP_ENV', false));

        if (file_exists(base_path('.git')) && env('APP_ENV', false) !== 'production') {
            $git_branch = exec('git rev-parse --abbrev-ref HEAD');
            View::share('git_branch', $git_branch);
        } else {
            View::share('git_branch', false);
        }

        // $this->bootBroadcast();
        // $this->bootEvent();

    } // end boot()

    public function bootBroadcast(): void
    {
        Broadcast::routes();

        include base_path('routes/channels.php');
    } // end bootBroadcast()

    public function bootEvent(): void
    {
        parent::boot();
    } // end bootEvent()

}// end class
