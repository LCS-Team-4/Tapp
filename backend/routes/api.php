<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClockController;
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

$router->post('/clock/token', [$tokenController, 'issue'], [Authenticate::class]);
$router->post('/clock/redeem', [$clockController, 'redeem']);

// Every other entry in frontend/shared/api/endpoints.js gets added here
// incrementally as its controller is built (steps 5-6), one route per
// feature, per architecture.md §8's recipe:
//   /clock/token, /clock/redeem                   -> TokenController,
//                                                     ClockController     (done)
//   /attendance                                   -> AttendanceService  (step 5)
//   /employees, /employees/{id}                   -> EmployeeController (step 5)
//   /feed                                         -> FeedController     (step 5)
//   /leave, /leave/{id}/decision                  -> LeaveController    (step 5)
//   /reports                                      -> ReportController   (step 6)
//   /settings                                     -> not yet scoped
//   /terminals                                    -> TerminalRepository (step 5/6)
// Don't pre-register routes for controllers that don't exist yet.
