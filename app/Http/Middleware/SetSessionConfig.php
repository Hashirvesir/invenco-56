<?php

// app/Http/Middleware/SetSessionConfig.php

namespace App\Http\Middleware;

use Closure;
use Config;

class SetSessionConfig
{
    public function handle($request, Closure $next)
    {
        // Protect against corrupted or obsolete cookies/headers crashing Passport with "The MAC is invalid"
        if ($request->hasHeader('X-XSRF-TOKEN')) {
            try {
                app('encrypter')->decrypt($request->header('X-XSRF-TOKEN'), false);
            } catch (\Throwable $e) {
                $request->headers->remove('X-XSRF-TOKEN');
            }
        }

        if ($request->hasCookie('laravel_token')) {
            try {
                app('encrypter')->decrypt($request->cookie('laravel_token'), false);
            } catch (\Throwable $e) {
                $request->cookies->remove('laravel_token');
            }
        }

        if ($request->is('online_store') || $request->is('online_store/*')) {
            Config::set('session.path', '/online_store');
            Config::set('session.cookie', 'store_session');
        } else {
            Config::set('session.path', '/');
            Config::set('session.cookie', 'web_session');
        }

        return $next($request);
    }
}
