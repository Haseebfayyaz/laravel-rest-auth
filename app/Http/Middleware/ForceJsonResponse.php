<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force Accept header to application/json for API requests
        $request->headers->set('Accept', 'application/json');
        
        $response = $next($request);
        
        // Ensure response is JSON
        if (!$response instanceof \Illuminate\Http\JsonResponse) {
            // If it's a redirect or other response, convert to JSON
            if ($response->isRedirect()) {
                return response()->json(['message' => 'Redirect required'], $response->getStatusCode());
            }
        }
        
        return $response;
    }
}
