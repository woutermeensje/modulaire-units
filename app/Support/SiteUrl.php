<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteUrl
{
    /**
     * Turn a path into a root-relative URL with the trailing slash the original WordPress site used, under the
     * prefix of the language ("/en/rent/"). Without a language the active one is used.
     */
    public static function path(string $path, ?string $locale = null): string
    {
        $path = trim(Locale::prefix($locale).'/'.trim($path, '/'), '/');

        return $path === '' ? '/' : '/'.$path.'/';
    }

    /**
     * Root-relative URL of a named page route in a language, with trailing slash: "units.index" gives "/units/"
     * on the Dutch site and the route "en.units.index" ("/en/units/") on the English site.
     *
     * @param  array<string, mixed>|string|int  $parameters
     */
    public static function route(string $name, array|string|int $parameters = [], ?string $locale = null): string
    {
        $url = static::action($name, $parameters, $locale);
        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);

        return rtrim($path, '/').'/'.($query !== null ? '?'.$query : '');
    }

    /**
     * Root-relative URL of a named route in a language without trailing slash, for form actions and fetch calls.
     *
     * @param  array<string, mixed>|string|int  $parameters
     */
    public static function action(string $name, array|string|int $parameters = [], ?string $locale = null): string
    {
        $localized = Locale::routeName($name, $locale);

        return route(Route::has($localized) ? $localized : $name, $parameters, false);
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
