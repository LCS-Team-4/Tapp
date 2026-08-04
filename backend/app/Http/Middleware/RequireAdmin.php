<?php

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

// Assumes Authenticate already ran (registered after it in a route's
// middleware array) — reads the user Authenticate attached to the request.
class RequireAdmin
{
    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user();

        if ($user === null || $user['role'] !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        return $next($request);
    }
}
