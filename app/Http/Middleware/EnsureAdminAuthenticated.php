<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof AdminUser) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
