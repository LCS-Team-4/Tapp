<?php
require "C:/xampp/htdocs/Tapp/backend/bootstrap/app.php";
$repo = new App\Repositories\SettingsRepository();
$settings = $repo->get();
