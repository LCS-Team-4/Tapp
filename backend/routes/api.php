<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClockController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TokenController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireEmployee;
use App\Http\Request;
use App\Http\Response;

/** @var \App\Http\Router $router */

$router->get('/health', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});

$authController = new AuthController();

$router->post('/auth/login', [$authController, 'login']);
$router->post('/auth/signup', [$authController, 'signup']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->get('/auth/session', [$authController, 'session'], [Authenticate::class]);

$tokenController = new TokenController();
$clockController = new ClockController();
$leaveController = new LeaveController();
$settingsController = new SettingsController();
$usersController = new UsersController();

$router->post('/clock/token', [$tokenController, 'issue'], [Authenticate::class, RequireEmployee::class]);
$router->post('/clock/redeem', [$clockController, 'redeem']);
$router->post('/clock/toggle', [$clockController, 'toggle'], [Authenticate::class, RequireEmployee::class]);

$router->post('/leave-request', [$leaveController, 'submit'], [Authenticate::class, RequireEmployee::class]);
$router->put('/leave-requests/{id}', [$leaveController, 'updateOwn'], [Authenticate::class, RequireEmployee::class]);
$router->delete('/leave-requests/{id}', [$leaveController, 'cancel'], [Authenticate::class, RequireEmployee::class]);
$router->get('/leave-requests', [$leaveController, 'getCalendar'], [Authenticate::class, RequireEmployee::class]);
$router->get('/admin/leave-requests', [$leaveController, 'getLeave'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/leave-requests/{id}', [$leaveController, 'updateStatus'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/leave-requests/{id}/updateRequest', [$leaveController, 'updateLeave'], [Authenticate::class, RequireAdmin::class]);

$router->get('/users', [$usersController, 'getCurrentUser'], [Authenticate::class]);
$router->get('/users/profile', [$usersController, 'profile'], [Authenticate::class, RequireEmployee::class]);
$router->get('/admin/dashboard', [$usersController, 'dashboard'], [Authenticate::class, RequireAdmin::class]);
$router->get('/admin/employees', [$usersController, 'employees'], [Authenticate::class, RequireAdmin::class]);
$router->post('/admin/employees', [$usersController, 'register'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/employees/{employeeId}', [$usersController, 'update'], [Authenticate::class, RequireAdmin::class]);
$router->delete('/admin/employees/{employeeId}', [$usersController, 'delete'], [Authenticate::class, RequireAdmin::class]);
$router->get('/admin/admins', [$usersController, 'admins'], [Authenticate::class, RequireAdmin::class]);
$router->post('/admin/admins/invite', [$usersController, 'inviteAdmin'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/admins/promote/{employeeId}', [$usersController, 'promoteToAdmin'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/admins/demote/{employeeId}', [$usersController, 'demoteFromAdmin'], [Authenticate::class, RequireAdmin::class]);
$router->get('/admin/settings', [$settingsController, 'get'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/settings', [$settingsController, 'update'], [Authenticate::class, RequireAdmin::class]);
