<?php

namespace App\Support;

/**
 * Versioned URLs for the hand-written files in public/ (there is no build step).
 *
 * A hash of the file's contents is appended as ?v=..., so browsers may cache css/js for a long
 * time yet fetch the new copy the moment a file changes. Modified times won't do: every file on
 * Vercel has the same fixed one. Use it in Blade as @asset('js/app.js').
 */
class Asset
{
    /** @var array<string, string> */
    private static array $urls = [];

    public static function url(string $path): string
    {
        $path = ltrim($path, '/');

        return self::$urls[$path] ??= '/'.$path.'?v='.(substr((string) @md5_file(public_path($path)), 0, 12) ?: '0');
    }
}
