<?php

namespace App\Services\WordPress;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the public WordPress REST API and rendered pages of the old site.
 * Responses are cached in storage/app/wp-import so a re-run is fast and works offline.
 */
class WordPressClient
{
    private string $cacheDirectory;

    public function __construct(private string $baseUrl, private bool $useCache = true)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cacheDirectory = storage_path('app/wp-import/cache');
        File::ensureDirectoryExists($this->cacheDirectory);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Fetch every item of a REST collection (follows pagination).
     *
     * @return list<array<string, mixed>>
     */
    public function collection(string $endpoint, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $url = $this->baseUrl.'/wp-json/wp/v2/'.$endpoint.'?'.http_build_query($query + ['per_page' => 100, 'page' => $page]);
            $body = $this->get($url);
            $batch = json_decode($body, true);

            if (! is_array($batch) || ! array_is_list($batch)) {
                break;
            }

            array_push($items, ...$batch);
            $page++;
        } while (count($batch) === 100);

        return $items;
    }

    public function html(string $url): string
    {
        return $this->get($url);
    }

    /**
     * Download files to the given absolute paths, a few at a time. Existing files are skipped.
     *
     * @param  array<string, string>  $downloads  remote URL => absolute local path
     * @return array{downloaded: int, skipped: int, failed: list<string>}
     */
    public function download(array $downloads, ?callable $progress = null): array
    {
        $result = ['downloaded' => 0, 'skipped' => 0, 'failed' => []];

        $pending = array_filter($downloads, function (string $path) use (&$result): bool {
            if (is_file($path) && filesize($path) > 0) {
                $result['skipped']++;

                return false;
            }

            return true;
        });

        foreach (array_chunk($pending, 8, true) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => collect($chunk)->map(
                fn (string $path, string $url) => $pool->as($url)->withUserAgent($this->userAgent())->timeout(60)->get($url)
            )->all());

            foreach ($chunk as $url => $path) {
                $response = $responses[$url] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    File::ensureDirectoryExists(dirname($path));
                    File::put($path, $response->body());
                    $result['downloaded']++;
                } else {
                    $result['failed'][] = $url;
                }

                if ($progress) {
                    $progress();
                }
            }
        }

        return $result;
    }

    private function get(string $url): string
    {
        $cacheFile = $this->cacheDirectory.'/'.sha1($url).'.cache';

        if ($this->useCache && is_file($cacheFile)) {
            return (string) file_get_contents($cacheFile);
        }

        $response = Http::withUserAgent($this->userAgent())->timeout(60)->retry(3, 1000, throw: false)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Ophalen mislukt ({$response->status()}): {$url}");
        }

        file_put_contents($cacheFile, $response->body());

        return $response->body();
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (compatible; ModulaireHuisvesting-Laravel-Importer/1.0)';
    }
}
