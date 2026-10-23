
<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve existing static files directly.
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($file)) {
    return false;
}

// Route all other requests through CodeIgniter.
require __DIR__ . '/index.php';
