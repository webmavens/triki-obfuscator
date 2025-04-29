<?php

declare(strict_types=1);

namespace WebMavens\Triki\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeTriki
{
    public function handle($request, Closure $next)
    {
        if (!Config::get('triki.auth.enabled', true)) {
            return $next($request);
        }

        if (!Gate::allows('viewTriki')) {
          abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
