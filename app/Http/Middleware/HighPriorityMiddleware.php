<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HighPriorityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        # Pause Horizon
        artisan('horizon:pause');
        $response = $next($request);
        
        # Resume Horizon
        artisan('horizon:continue');
        return $response;
    }
}
