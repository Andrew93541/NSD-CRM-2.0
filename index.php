<?php

/**
 * NSD CRM 2.0 — Root Directory Fallback Front Controller (cPanel / XAMPP)
 *
 * For standard production deployments, point the web server DocumentRoot to /public.
 * When running inside XAMPP (e.g. htdocs/NSD-CRM-2.0) or cPanel shared hosting where
 * DocumentRoot cannot be pointed to /public, this file safely proxies requests to
 * public/index.php while blocking access to internal paths and serving public build assets.
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

$relativePath = $requestUri;
if ($scriptDir !== '' && str_starts_with($relativePath, $scriptDir)) {
    $relativePath = substr($relativePath, strlen($scriptDir)) ?: '/';
}
$relativePath = '/' . ltrim($relativePath, '/');

// Defense-in-depth: block sensitive paths even if Apache .htaccess is bypassed
$blockedPattern = '#^/(?:\.(?!well-known(?:/|$))|artisan$|composer\.(?:json|lock)$|package(?:-lock)?\.json$|phpunit\.xml|vite\.config\.js$|(?:app|bootstrap|config|database|docs|lang|node_modules|resources|routes|storage|tests|vendor)(?:/|$)|.*\.(?:sql|sqlite|sqlite3|log|bak|env)$)#i';
if (preg_match($blockedPattern, $relativePath)) {
    http_response_code(403);
    exit('403 Forbidden');
}

// Serve static public assets (e.g. /build/assets/*, /favicon.ico, /robots.txt) if not rewritten by Apache
if ($relativePath !== '/' && $relativePath !== '/index.php') {
    $publicBase = realpath(__DIR__ . '/public');
    $candidate = $publicBase ? realpath($publicBase . $relativePath) : false;

    if (
        $candidate !== false
        && $publicBase !== false
        && str_starts_with($candidate, $publicBase . DIRECTORY_SEPARATOR)
        && is_file($candidate)
        && pathinfo($candidate, PATHINFO_EXTENSION) !== 'php'
    ) {
        $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $mimeMap = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'txt' => 'text/plain; charset=UTF-8',
        ];
        header('Content-Type: ' . ($mimeMap[$ext] ?? (mime_content_type($candidate) ?: 'application/octet-stream')));
        header('Content-Length: ' . filesize($candidate));
        readfile($candidate);
        exit;
    }
}

require __DIR__ . '/public/index.php';

