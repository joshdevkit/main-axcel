<?php

namespace Axcel\Http\Middleware;

use Axcel\Http\Request;

interface Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Axcel\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, \Closure $next);
}
