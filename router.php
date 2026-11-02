<?php

$raw_path = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$path = rawurldecode($raw_path);

// Serve existing static files directly. Decode %20 so names with spaces resolve.
if ($path !== '/' && strpos($path, '..') === false) {
    $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

    if (is_file($file)) {
        if ($path === $raw_path) {
            return false;
        }

        $types = array(
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'svg'  => 'image/svg+xml',
        );
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        header('Content-Type: '.($types[$extension] ?? 'application/octet-stream'));
        header('Content-Length: '.filesize($file));
        readfile($file);
        return true;
    }
}

// Route all other requests through CodeIgniter.
require __DIR__ . '/index.php';
