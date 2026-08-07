<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClockController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\TokenController;
use App\Http\Middleware\Authenticate;
use App\Http\Request;
use App\Http\Response;

/** @var \App\Http\Router $router */

$router->get('/health', function (Request $request): Response {
    return Response::json(['status' => 'ok']);
});

$authController = new AuthController();

$router->post('/auth/login', [$authController, 'login']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->get('/auth/session', [$authController, 'session'], [Authenticate::class]);

$tokenController = new TokenController();
$clockController = new ClockController();
$leaveController = new LeaveController();
$usersController = new usersController();

$router->post('/clock/token', [$tokenController, 'issue'], [Authenticate::class]);
$router->post('/clock/redeem', [$clockController, 'redeem']);

$router->post('/leave-request', [$leaveController, 'submit'], [Authenticate::class]);
$router->get('/leave-requests', [$leaveController, 'getCalendar'], [Authenticate::class]);
$router->get('/admin/leave-requests', [$leaveController, 'getLeave'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/leave-requests/{id}', [$leaveController, 'updateStatus'], [Authenticate::class, RequireAdmin::class]);
$router->put('/admin/leave-requests/{id}/updateRequest', [$leaveController, 'updateLeave'], [Authenticate::class, RequireAdmin::class]);

$router->get('/users', [$usersController, 'getCurrentUser'], [Authenticate::class]);

// Every other entry in frontend/shared/api/endpoints.js gets added here
// incrementally as its controller is built (steps 5-6), one route per
// feature, per architecture.md §8's recipe:
//   /clock/token, /clock/redeem                   -> TokenController,
//                                                     ClockController     (done)
//   /attendance                                   -> AttendanceService  (step 5)
//   /employees, /employees/{id}                   -> EmployeeController (step 5)
//   /feed                                         -> FeedController     (step 5)
//   /reports                                      -> ReportController   (step 6)
//   /settings                                     -> not yet scoped
//   /terminals                                    -> TerminalRepository (step 5/6)
// Don't pre-register routes for controllers that don't exist yet.
