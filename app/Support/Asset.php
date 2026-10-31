<?php

namespace App\Support;

/**
 * Versioned URLs for the hand-written files in public/ (there is no build step).
 *
 * The file's modified time is appended as ?v=..., so browsers may cache css/js for a long
 * time yet fetch the new copy the moment a file changes. Use it in Blade as @asset('js/app.js').
 */
class Asset
{
    /** @var array<string, string> */
    private static array $urls = [];

    public static function url(string $path): string
    {
        $path = ltrim($path, '/');

        return self::$urls[$path] ??= '/'.$path.'?v='.(@filemtime(public_path($path)) ?: '0');
    }
}
