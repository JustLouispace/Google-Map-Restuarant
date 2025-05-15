<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheControl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Set cache control headers for API responses
        if ($request->is('api/*')) {
            // Cache API responses for 5 minutes
            $response->header('Cache-Control', 'public, max-age=300');
        }
        
        return $response;
    }
}
