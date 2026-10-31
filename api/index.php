<?php

/*
| Vercel serverless entry point (vercel-php runtime). Locally the app is served from
| public/index.php as usual; this file only adapts the request to Vercel's environment.
*/
if (getenv('VERCEL')) {
    // The function lives at /api/index.php. Without this, Laravel treats "/api" as the base
    // path and strips it from every request, so all /api/* routes would 404.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';

    // Neon routes connections by SNI, which the runtime's libpq doesn't send. Pass the
    // endpoint id (first label of the DB host) through PGOPTIONS instead, as Neon documents.
    $dbUrl = getenv('DB_URL') ?: getenv('DATABASE_URL');
    if ($dbUrl && ($dbHost = parse_url($dbUrl, PHP_URL_HOST))) {
        $endpoint = str_replace('-pooler', '', explode('.', $dbHost)[0]);
        if (str_starts_with($endpoint, 'ep-')) {
            putenv("PGOPTIONS=endpoint={$endpoint}");
            $_ENV['PGOPTIONS'] = $_SERVER['PGOPTIONS'] = "endpoint={$endpoint}";
        }
    }

    // Only /tmp is writable: storage (see bootstrap/app.php) and Laravel's bootstrap caches go there.
    foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
        @mkdir("/tmp/storage/{$dir}", 0777, true);
    }
    @mkdir('/tmp/bootstrap', 0777, true);
    foreach (['PACKAGES', 'SERVICES', 'CONFIG', 'ROUTES', 'EVENTS'] as $cache) {
        $path = '/tmp/bootstrap/'.strtolower($cache).'.php';
        putenv("APP_{$cache}_CACHE={$path}");
        $_ENV["APP_{$cache}_CACHE"] = $_SERVER["APP_{$cache}_CACHE"] = $path;
    }
}

require __DIR__.'/../public/index.php';
