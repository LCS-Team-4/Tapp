<?php

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\TokenService;

class TokenController
{
    public function issue(Request $request): Response
    {
        $userId = (int) $request->user()['id'];
        $token = (new TokenService())->issue($userId);

        return Response::json(['token' => $token, 'expires_in' => 30]);
    }
}
