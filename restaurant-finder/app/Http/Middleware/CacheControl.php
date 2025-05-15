// In app/Http/Middleware/CacheControl.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheControl
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Set cache control headers for API responses
        if ($request->is('api/*')) {
            $response->header('Cache-Control', 'public, max-age=60');
        }
        
        return $response;
    }
}