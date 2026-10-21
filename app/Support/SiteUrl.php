<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
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

        if (str_starts_with($path, '/storage/media/')) {
            $path = substr($path, strlen('/storage/'));
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        if (config('filesystems.disks.public.driver') !== 'local') {
            return Storage::disk('public')->url($path);
        }

        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Resolve embedded image, srcset and CSS URLs in imported content without changing the stored content.
     */
    public static function rewriteMediaUrls(string $markup): string
    {
        if (config('filesystems.disks.public.driver') === 'local' || ! str_contains($markup, '/storage/media/')) {
            return $markup;
        }

        $baseUrl = rtrim(Storage::disk('public')->url('media'), '/').'/';

        return preg_replace_callback('~(?<![a-zA-Z0-9:/._-])/storage/media/~', fn (): string => $baseUrl, $markup);
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
