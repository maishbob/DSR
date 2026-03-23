<?php

define('LARAVEL_START', microtime(true));

// Point to the Laravel app installed outside public_html
require '/home/tosnaxan/dsr/backend/vendor/autoload.php';

$app = require_once '/home/tosnaxan/dsr/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);
