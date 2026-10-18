<?php

namespace App\Services\WordPress;

use Illuminate\Support\Str;

/**
 * Rewrites absolute WordPress URLs to root-relative URLs of the Laravel site and collects
 * every upload that is referenced so the importer can download it.
 */
class UrlRewriter
{
    /** @var array<string, string> remote URL => local media path */
    private array $uploads = [];

    public function __construct(private string $sourceUrl = 'https://modulairehuisvesting.nl') {}

    public function sourceHost(): string
    {
        return (string) parse_url($this->sourceUrl, PHP_URL_HOST);
    }

    /**
     * Map a WordPress upload URL (any size variant) to the local media path, e.g. "media/2026/05/foto.png".
     */
    public function uploadPath(string $url): ?string
    {
        $url = html_entity_decode(trim($url));
        $url = preg_split('/[?#]/', $url)[0];

        if (! str_contains($url, '/wp-content/uploads/')) {
            return null;
        }

        if (preg_match('#^(?:https?:)?//([^/]+)#i', $url, $host) && ! Str::endsWith(strtolower($host[1]), $this->sourceHost())) {
            return null;
        }

        $relative = rawurldecode(Str::after($url, '/wp-content/uploads/'));

        // Invisible characters (soft hyphens etc.) are not allowed in local file names.
        $relative = (string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', $relative);

        if ($relative === '' || ! mb_check_encoding($relative, 'UTF-8')) {
            return null;
        }

        $this->uploads[$this->absolute('/wp-content/uploads/'.Str::after($url, '/wp-content/uploads/'))] = 'media/'.$relative;

        return 'media/'.$relative;
    }

    /**
     * @return array<string, string>
     */
    public function uploads(): array
    {
        return $this->uploads;
    }

    public function rewriteHtml(string $html): string
    {
        $host = preg_quote($this->sourceHost(), '#');

        $html = preg_replace_callback(
            '#(?:https?:)?(?:\\\\?/){2}(?:www\.)?'.$host.'(?:\\\\?/)wp-content(?:\\\\?/)uploads(?:\\\\?/)[^\s"\'()<>,]+#i',
            function (array $match): string {
                $path = $this->uploadPath(str_replace('\\/', '/', $match[0]));

                return $path === null ? $match[0] : '/storage/'.$path;
            },
            $html
        );

        $html = preg_replace('#(href|action)=(["\'])(?:https?:)?//(?:www\.)?'.$host.'(/[^"\']*)?\2#i', '$1=$2$3$2', $html);
        $html = preg_replace('#(href|action)=(["\'])(["\'])#', '$1=$2/$3', $html);

        return preg_replace('#(?:https?:)?//(?:www\.)?'.$host.'(?=/|["\'\s<])#i', '', $html);
    }

    public function rewriteCss(string $css): string
    {
        return preg_replace_callback('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', function (array $match): string {
            $path = $this->uploadPath($match[2]);

            return $path === null ? $match[0] : "url('/storage/{$path}')";
        }, $css);
    }

    private function absolute(string $path): string
    {
        return rtrim($this->sourceUrl, '/').'/'.ltrim($path, '/');
    }
}
