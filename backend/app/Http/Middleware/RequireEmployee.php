<?php

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/** Limits employee-facing endpoints to a logged-in employee. */
class RequireEmployee
{
    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user();

        if ($user === null || ($user['role'] ?? null) !== 'employee') {
            return Response::error('Forbidden', 403);
        }

        return $next($request);
    }
}
