<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdultAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $password = config('services.adult.password');

        if (! is_string($password) || $password === '') {
            if (app()->environment('testing')) {
                return $next($request);
            }

            abort(503, '成人内容访问尚未配置。');
        }

        $authorizedUntil = $request->session()->get('adult.authorized_until');

        if (is_int($authorizedUntil) && $authorizedUntil > now()->getTimestamp()) {
            return $next($request);
        }

        $request->session()->forget('adult.authorized_until');
        $request->session()->put('adult.intended_url', '/'.$request->path().($request->getQueryString() ? '?'.$request->getQueryString() : ''));

        return redirect()->route('movies.index', ['adult_auth' => 1]);
    }
}
