<?php

use App\Support\Env;

$origins = Env::get('CORS_ALLOWED_ORIGINS', '');

return [
    'allowed_origins' => $origins === '' ? [] : array_map('trim', explode(',', $origins)),
];
