<?php

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;

class UsersController
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    public function getCurrentUser(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        return Response::json($user);
    }
}