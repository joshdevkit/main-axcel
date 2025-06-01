<?php

namespace Axcel\Http\Middleware;

use Axcel\Facades\Auth;

class GuestMiddleware implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Axcel\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard')->with('error', 'You are already logged in.');
        }

        return $next($request);
    }
}
