<?php

namespace App\Support;

use Illuminate\Support\Str;

class SiteUrl
{
    /**
     * Turn a path into a root-relative URL with the trailing slash the original WordPress site used.
     */
    public static function path(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '/' : '/'.$path.'/';
    }

    /**
     * Resolve a stored media path (relative to the public disk) to a public URL.
     */
    public static function media(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Absolute URL for canonical tags, sitemaps and Open Graph.
     */
    public static function absolute(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
