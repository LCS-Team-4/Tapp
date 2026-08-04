<?php

use App\Support\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'base_url' => Env::get('APP_BASE_URL', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'api_base_path' => Env::get('APP_API_BASE_PATH', '/api'),
];
