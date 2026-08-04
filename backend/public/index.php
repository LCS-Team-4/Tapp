<?php

use App\Http\Request;

$router = require __DIR__ . '/../bootstrap/app.php';

$response = $router->dispatch(Request::fromGlobals());
$response->send();
