<?php

namespace Axcel\Http\Middleware;

use Axcel\Facades\Auth;
use Axcel\Http\Request;
use Closure;

class AuthMiddleware implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'You must be logged in to access this page.');
        }

        return $next($request);
    }
}
