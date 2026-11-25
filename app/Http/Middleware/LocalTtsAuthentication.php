<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;

class LocalTtsAuthentication extends Authenticate
{
    public function handle($request, Closure $next, ...$guards)
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
