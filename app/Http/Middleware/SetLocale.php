<?php

namespace App\Http\Middleware;

use App\Services\LocalizedLinks;
use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Activates the language of a group of public routes. On a translated site, internal links in the HTML that still
 * point to Dutch pages (in page content, menus and fixed links in templates) are pointed to their translation.
 */
class SetLocale
{
    public function __construct(private LocalizedLinks $links) {}

    public function handle(Request $request, Closure $next, string $locale = Locale::SOURCE): Response
    {
        Locale::activate($locale);

        $response = $next($request);

        if (Locale::isSource($locale)) {
            return $response;
        }

        if ($response->isRedirection() && ($location = $response->headers->get('Location'))) {
            $response->headers->set('Location', $this->links->localizeUrl($location, $locale));
        }

        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $content = $response->getContent();

            if (is_string($content) && $content !== '') {
                $response->setContent($this->links->localize($content, $locale));
            }
        }

        return $response;
    }
}
