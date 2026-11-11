<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureRolePrefixMatches
{
    public function handle(Request $request, Closure $next): Response
    {
        $userPrefix = $request->user()?->rolePrefix() ?? 'member';

        URL::defaults(['rolePrefix' => $userPrefix]);

        $route = $request->route();

        if ($route === null || ! $route->hasParameter('rolePrefix')) {
            return $next($request);
        }

        $prefix = $route->parameter('rolePrefix');

        if ($prefix !== $userPrefix) {
            $segments = $request->segments();
            array_shift($segments);
            $target = '/'.$userPrefix.($segments === [] ? '' : '/'.implode('/', $segments));
            $query = $request->getQueryString();

            return redirect()->to($target.($query !== null && $query !== '' ? '?'.$query : ''));
        }

        // shortcut: prefix must not leak into controller signatures as a positional route parameter
        $route->forgetParameter('rolePrefix');

        return $next($request);
    }
}
