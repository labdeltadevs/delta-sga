<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsForNgrok
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            str_ends_with($request->getHost(), '.ngrok-free.app')
            || str_ends_with($request->getHost(), '.ngrok.io')
        ) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
