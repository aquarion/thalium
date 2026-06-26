<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies;

    protected $headers = (Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_AWS_ELB);

    // Default covers Docker's bridge network CIDR; override with TRUSTED_PROXIES env var (comma-separated CIDRs, or '*')
    public function __construct()
    {
        $value = env('TRUSTED_PROXIES', '172.16.0.0/12');
        $this->proxies = $value === '*' ? '*' : array_map('trim', explode(',', $value));
    }
}
