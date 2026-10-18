<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\TeamMember;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\Vacancy;
use App\Services\WordPress\CssCleaner;
use App\Services\WordPress\HtmlTransformer;
use App\Services\WordPress\UrlRewriter;
use App\Services\WordPress\WordPressClient;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Signature('wp:import
    {--source= : Basis-URL van de WordPress-site (standaard WP_IMPORT_SOURCE)}
    {--fresh : Negeer de lokale cache en haal alles opnieuw op}
    {--skip-media : Download geen afbeeldingen}')]
#[Description('Importeer alle pagina\'s, blogs, units, producten, vacatures, team, menu\'s en media van de WordPress-site')]
class ImportWordPress extends Command
{
    private WordPressClient $client;

    private UrlRewriter $urls;

    private HtmlTransformer $html;

    private CssCleaner $css;

    /** @var array<int, string> WordPress media ID => local media path */
    private array $mediaPaths = [];

    /** @var array<int, string|null> WordPress media ID => alt text */
    private array $mediaAlts = [];

    /** @var array<string, HTMLDocument> */
    private array $documents = [];

    public function handle(): int
    {
        $source = rtrim($this->option('source') ?: env('WP_IMPORT_SOURCE', 'https://modulairehuisvesting.nl'), '/');

        $this->client = new WordPressClient($source, ! $this->option('fresh'));
        $this->urls = new UrlRewriter($source);
        $this->html = new HtmlTransformer($this->urls);
        $this->css = new CssCleaner($this->urls);

        $this->components->info("Importeren vanaf {$source}");

        $steps = [
            'Media' => fn () => $this->importMedia(),
            'Unit-kenmerken' => fn () => $this->importTerms(),
            'Auteurs & categorieën' => fn () => $this->importAuthorsAndCategories(),
            "Pagina's" => fn () => $this->importPages(),
            'Blogs' => fn () => $this->importPosts(),
            'Units' => fn () => $this->importUnits(),
            'Producten' => fn () => $this->importProducts(),
            'Vacatures' => fn () => $this->importVacancies(),
            'Team' => fn () => $this->importTeam(),
            "Menu's & instellingen" => fn () => $this->importMenusAndSettings(),
            'Redirects' => fn () => $this->importRedirects(),
        ];

        foreach ($steps as $label => $step) {
            $this->components->task($label, $step);
        }

        Cache::forget('settings');

        if (! $this->option('skip-media')) {
            $this->downloadUploads();
        }

        $this->components->info('Import afgerond.');

        return self::SUCCESS;
    }

    private function importMedia(): void
    {
        foreach ($this->client->collection('media') as $item) {
            $path = $this->urls->uploadPath($item['source_url']);
            if ($path === null) {
                continue;
            }

            $sizes = [];
            foreach ($item['media_details']['sizes'] ?? [] as $name => $size) {
                if ($name !== 'full' && in_array($name, ['thumbnail', 'medium', 'medium_large', 'large'], true)) {
                    $sizes[$name] = $this->urls->uploadPath($size['source_url']);
                }
            }

            Media::query()->updateOrCreate(['wp_id' => $item['id']], [
                'path' => $path,
                'title' => html_entity_decode(strip_tags($item['title']['rendered'] ?? '')),
                'alt' => $item['alt_text'] ?: null,
                'caption' => trim(strip_tags($item['caption']['rendered'] ?? '')) ?: null,
                'mime_type' => $item['mime_type'] ?? null,
                'width' => $item['media_details']['width'] ?? null,
                'height' => $item['media_details']['height'] ?? null,
                'size' => $item['media_details']['filesize'] ?? null,
                'sizes' => $sizes ?: null,
            ]);

            $this->mediaPaths[$item['id']] = $path;
            $this->mediaAlts[$item['id']] = $item['alt_text'] ?: null;
        }
    }

    private function importTerms(): void
    {
        $taxonomies = [
            'mh_unit_type' => UnitTerm::TYPE,
            'mh_unit_conditie' => UnitTerm::CONDITION,
            'mh_unit_aanbod' => UnitTerm::OFFER,
        ];

        foreach ($taxonomies as $endpoint => $taxonomy) {
            foreach ($this->client->collection($endpoint) as $term) {
                UnitTerm::query()->updateOrCreate(['wp_id' => $term['id']], [
                    'taxonomy' => $taxonomy,
                    'name' => html_entity_decode($term['name']),
                    'slug' => $term['slug'],
                    'description' => $term['description'] ?: null,
                ]);
            }
        }
    }

    private function importAuthorsAndCategories(): void
    {
        foreach ($this->client->collection('users') as $user) {
            Author::query()->updateOrCreate(['wp_id' => $user['id']], [
                'name' => html_entity_decode($user['name']),
                'slug' => $user['slug'],
                'bio' => $user['description'] ?: null,
            ]);
        }

        foreach ($this->client->collection('categories') as $category) {
            Category::query()->updateOrCreate(['wp_id' => $category['id']], [
                'name' => html_entity_decode($category['name']),
                'slug' => $category['slug'],
                'description' => $category['description'] ?: null,
            ]);
        }
    }

    /**
     * WooCommerce/MailPoet system pages that are replaced by Laravel routes or redirects.
     *
     * @var list<string>
     */
    private const SKIPPED_PAGES = ['winkelwagen', 'mijn-account', 'mijn-offerte', 'winkel', 'afrekenen', 'checkout'];

    private function importPages(): void
    {
        $items = collect($this->client->collection('pages'))->sortBy(fn (array $page): int => substr_count($this->pathFromLink($page['link']), '/'));
        $pagesByWpId = [];

        foreach ($items as $item) {
            $path = $this->pathFromLink($item['link']);

            if (in_array($path, self::SKIPPED_PAGES, true)) {
                continue;
            }

            $rendered = $this->parsePage($item['link']);

            // The blog overview is rendered by BlogController; the WordPress archive HTML is not needed.
            if ($path === 'blog') {
                $rendered['content'] = '';
            }

            $page = Page::query()->firstOrNew(['wp_id' => $item['id']]);
            $page->fill([
                'parent_id' => $item['parent'] ? ($pagesByWpId[$item['parent']] ?? null) : null,
                'title' => html_entity_decode($item['title']['rendered']),
                'slug' => $path === '' ? '' : Str::afterLast($path, '/'),
                'template' => $rendered['hero'] ? 'landing' : 'default',
                'show_title' => $rendered['show_title'],
                'hero' => $rendered['hero'],
                'content' => $rendered['content'],
                'custom_css' => $rendered['css'],
                'excerpt' => trim(html_entity_decode(strip_tags($item['excerpt']['rendered'] ?? ''))) ?: null,
                'featured_image' => $this->mediaPaths[$item['featured_media']] ?? $rendered['tile_image'] ?? null,
                'status' => $item['status'] === 'publish' ? 'published' : 'draft',
                'menu_order' => $item['menu_order'] ?? 0,
                'published_at' => Carbon::parse($item['date_gmt'], 'UTC'),
            ] + $this->seo($item));
            $page->save();

            if ($page->path !== $path) {
                $this->components->warn("Pad wijkt af voor {$path}: {$page->path}");
            }

            $pagesByWpId[$item['id']] = $page->id;
        }

        $this->fillTileImages();
    }

    /**
     * Pages without featured image get the image WordPress showed on their tile in a [subpage_grid].
     */
    private function fillTileImages(): void
    {
        foreach (['', 'aanbod'] as $path) {
            $document = $this->documents[$this->absoluteUrl($path)] ?? null;
            foreach ($document?->querySelectorAll('a.muo-card') ?? [] as $card) {
                preg_match("/url\\('([^']+)'\\)/", (string) $card->getAttribute('style'), $match);
                $tilePath = isset($match[1]) ? $this->urls->uploadPath($match[1]) : null;
                $target = $this->pathFromLink((string) $card->getAttribute('href'));

                if ($tilePath) {
                    Page::query()->where('path', $target)->whereNull('featured_image')->update(['featured_image' => $tilePath]);
                }
            }
        }
    }

    /**
     * @return array{content: string, css: ?string, hero: ?array<string, string|null>, show_title: bool, tile_image: ?string}
     */
    private function parsePage(string $url): array
    {
        $document = $this->document($url);
        $body = $document->body;

        $nodes = [];
        $collecting = false;
        foreach (iterator_to_array($body->childNodes) as $node) {
            if ($node instanceof Element && $node->id === 'mh-cta-bar') {
                $collecting = true;

                continue;
            }
            if ($node instanceof Element && $node->localName === 'footer') {
                break;
            }
            if ($collecting) {
                $nodes[] = $node;
            }
        }

        $hero = null;
        $showTitle = false;
        $html = '';

        foreach ($nodes as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            if ($node->classList->contains('mh-lp') && ($heroElement = $node->querySelector('.mh-home-hero'))) {
                $hero = $this->parseHero($heroElement);

                continue;
            }

            if ($node->localName === 'main') {
                $header = $node->querySelector('.page-header .entry-title');
                $showTitle = $header !== null && trim($header->textContent) !== '';
                $content = $node->querySelector('.page-content');
                $html .= $content ? $content->innerHTML : $node->innerHTML;

                continue;
            }

            if (in_array($node->localName, ['script', 'style', 'link'], true)) {
                continue;
            }

            $html .= $document->saveHtml($node);
        }

        return [
            'content' => $this->html->transform($html),
            'css' => $this->pageCss($document),
            'hero' => $hero,
            'show_title' => $showTitle,
            'tile_image' => null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function parseHero(Element $hero): array
    {
        preg_match("/url\\('([^']+)'\\)/", (string) $hero->getAttribute('style'), $image);
        $button = $hero->querySelector('.mh-home-hero__actions a');

        return [
            'image' => isset($image[1]) ? $this->urls->uploadPath($image[1]) : null,
            'eyebrow' => trim((string) $hero->querySelector('.mh-home-hero__eyebrow')?->textContent) ?: null,
            'title' => trim((string) $hero->querySelector('.mh-home-hero__title')?->innerHTML) ?: null,
            'subtitle' => trim((string) $hero->querySelector('.mh-home-hero__subtitle')?->textContent) ?: null,
            'button_text' => trim((string) $button?->textContent) ?: null,
            'button_url' => $button ? $this->localUrl((string) $button->getAttribute('href')) : null,
        ];
    }

    /**
     * Elementor writes the layout of every page to uploads/elementor/css/post-{id}.css.
     */
    private function pageCss(HTMLDocument $document): ?string
    {
        $css = '';
        foreach ($document->querySelectorAll('link[rel="stylesheet"][href*="/elementor/css/post-"]') as $link) {
            $href = (string) $link->getAttribute('href');
            if (preg_match('/post-(6|18)\.css/', $href)) {
                continue;
            }
            try {
                $css .= $this->css->clean($this->client->html($href))."\n";
            } catch (Throwable) {
                continue;
            }
        }

        return trim($css) ?: null;
    }

    private function importPosts(): void
    {
        $authors = Author::query()->pluck('id', 'wp_id');
        $categories = Category::query()->pluck('id', 'wp_id');

        foreach ($this->client->collection('posts') as $item) {
            Post::query()->updateOrCreate(['wp_id' => $item['id']], [
                'author_id' => $authors[$item['author']] ?? null,
                'category_id' => $categories[$item['categories'][0] ?? 0] ?? null,
                'title' => html_entity_decode($item['title']['rendered']),
                'slug' => $item['slug'],
                'excerpt' => $this->plainExcerpt($item['excerpt']['rendered'] ?? ''),
                'content' => $this->html->transform($item['content']['rendered']),
                'featured_image' => $this->mediaPaths[$item['featured_media']] ?? null,
                'featured_image_alt' => $this->mediaAlts[$item['featured_media']] ?? null,
                'status' => $item['status'] === 'publish' ? 'published' : 'draft',
                'published_at' => Carbon::parse($item['date_gmt'], 'UTC'),
            ] + $this->seo($item));
        }
    }

    private function importUnits(): void
    {
        $terms = UnitTerm::query()->pluck('id', 'wp_id');

        foreach ($this->client->collection('mh_unit') as $index => $item) {
            $document = $this->document($item['link']);

            $gallery = [];
            foreach ($document->querySelectorAll('#mh-lightbox-strip img') as $image) {
                $path = $this->urls->uploadPath((string) $image->getAttribute('src'));
                if ($path) {
                    $gallery[] = ['path' => $path, 'alt' => $image->getAttribute('alt') ?: null];
                }
            }

            $prices = [];
            foreach ($document->querySelectorAll('.mh-price-card') as $card) {
                $prices[trim((string) $card->querySelector('.mh-price-card__label')?->textContent)] = trim((string) $card->querySelector('.mh-price-card__value')?->textContent);
            }

            $featured = $this->mediaPaths[$item['featured_media']] ?? ($gallery[0]['path'] ?? null);

            $unit = Unit::query()->updateOrCreate(['wp_id' => $item['id']], [
                'title' => html_entity_decode($item['title']['rendered']),
                'slug' => $item['slug'],
                'excerpt' => $this->plainExcerpt($item['excerpt']['rendered'] ?? ''),
                'content' => $this->html->transform($item['content']['rendered']),
                'featured_image' => $featured,
                'featured_image_alt' => $this->mediaAlts[$item['featured_media']] ?? null,
                'gallery' => array_values(array_filter($gallery, fn (array $image): bool => $image['path'] !== $featured)),
                'price_rent' => $prices['Huren'] ?? null,
                'price_buy' => $prices['Kopen'] ?? null,
                'status' => $item['status'] === 'publish' ? 'published' : 'draft',
                'sort' => $index,
                'published_at' => Carbon::parse($item['date_gmt'], 'UTC'),
            ] + $this->seo($item));

            $termIds = collect(['mh_unit_type', 'mh_unit_conditie', 'mh_unit_aanbod'])
                ->flatMap(fn (string $taxonomy): array => $item[$taxonomy] ?? [])
                ->map(fn (int $wpId): ?int => $terms[$wpId] ?? null)
                ->filter()
                ->all();

            $unit->terms()->sync($termIds);
        }
    }

    private function importProducts(): void
    {
        foreach ($this->client->collection('product') as $index => $item) {
            $document = $this->document($item['link']);

            $gallery = [];
            foreach ($document->querySelectorAll('.woocommerce-product-gallery__image a') as $link) {
                $path = $this->urls->uploadPath((string) $link->getAttribute('href'));
                if ($path && ! collect($gallery)->contains('path', $path)) {
                    $gallery[] = ['path' => $path, 'alt' => $link->querySelector('img')?->getAttribute('alt') ?: null];
                }
            }

            $description = $document->querySelector('#tab-description');
            $description?->querySelector('h2')?->remove();
            $short = $document->querySelector('.woocommerce-product-details__short-description');
            $price = trim((string) $document->querySelector('.summary .price')?->textContent);
            $featured = $this->mediaPaths[$item['featured_media']] ?? ($gallery[0]['path'] ?? null);

            Product::query()->updateOrCreate(['wp_id' => $item['id']], [
                'title' => html_entity_decode($item['title']['rendered']),
                'slug' => $item['slug'],
                'short_description' => $short ? $this->html->transform($short->innerHTML) : null,
                'content' => $description ? $this->html->transform($description->innerHTML) : $this->html->transform($item['content']['rendered']),
                'featured_image' => $featured,
                'featured_image_alt' => $this->mediaAlts[$item['featured_media']] ?? null,
                'gallery' => array_values(array_filter($gallery, fn (array $image): bool => $image['path'] !== $featured)),
                'price' => $price ?: null,
                'status' => $item['status'] === 'publish' ? 'published' : 'draft',
                'sort' => $index,
                'published_at' => Carbon::parse($item['date_gmt'], 'UTC'),
            ] + $this->seo($item));
        }
    }

    private function importVacancies(): void
    {
        foreach ($this->client->collection('job-listings') as $item) {
            Vacancy::query()->updateOrCreate(['wp_id' => $item['id']], [
                'title' => html_entity_decode($item['title']['rendered']),
                'slug' => $item['slug'],
                'content' => $this->html->transform($item['content']['rendered']),
                'location' => ($item['meta']['_job_location'] ?? null) ?: null,
                'featured_image' => $this->mediaPaths[$item['featured_media']] ?? null,
                'is_filled' => (bool) ($item['meta']['_filled'] ?? false),
                'status' => $item['status'] === 'publish' ? 'published' : 'draft',
                'published_at' => Carbon::parse($item['date_gmt'], 'UTC'),
            ] + $this->seo($item));
        }
    }

    private function importTeam(): void
    {
        $cards = [];
        try {
            foreach ($this->document($this->absoluteUrl('ons-team'))->querySelectorAll('.mh-team-card') as $card) {
                $email = null;
                if ($link = $card->querySelector('a[href*="email-protection"]')) {
                    $email = HtmlTransformer::decodeCloudflareEmail(Str::after((string) $link->getAttribute('href'), '#'));
                }
                $cards[trim((string) $card->querySelector('.mh-team-card__name')?->textContent)] = [
                    'role' => trim((string) $card->querySelector('.mh-team-card__role')?->textContent) ?: null,
                    'email' => $email,
                    'phone' => trim((string) $card->querySelector('.mh-team-card__contact-link--phone')?->textContent) ?: null,
                ];
            }
        } catch (Throwable) {
            // The team page is optional; members are still imported from the API.
        }

        $order = array_flip(array_keys($cards));

        foreach ($this->client->collection('mh_team_member') as $item) {
            $name = html_entity_decode($item['title']['rendered']);

            TeamMember::query()->updateOrCreate(['wp_id' => $item['id']], [
                'name' => $name,
                'role' => $cards[$name]['role'] ?? null,
                'email' => $cards[$name]['email'] ?? null,
                'phone' => $cards[$name]['phone'] ?? null,
                'photo' => $this->mediaPaths[$item['featured_media']] ?? null,
                'sort' => $order[$name] ?? ($item['menu_order'] ?? 99),
                'is_visible' => $item['status'] === 'publish',
            ]);
        }
    }

    private function importMenusAndSettings(): void
    {
        $document = $this->document($this->absoluteUrl(''));

        MenuItem::query()->delete();

        foreach ($document->querySelectorAll('#menu-hoofdmenu > li') as $index => $item) {
            $link = $item->querySelector('a');
            $parent = MenuItem::query()->create([
                'menu' => 'main',
                'label' => trim((string) $link?->textContent),
                'url' => $this->localUrl((string) $link?->getAttribute('href')),
                'sort' => $index,
            ]);

            foreach ($item->querySelectorAll('ul.mh-nav__dropdown a') as $childIndex => $child) {
                MenuItem::query()->create([
                    'menu' => 'main',
                    'parent_id' => $parent->id,
                    'label' => trim((string) $child->textContent),
                    'url' => $this->localUrl((string) $child->getAttribute('href')),
                    'sort' => $childIndex,
                ]);
            }

            if ($parent->label === 'Contact') {
                MenuItem::query()->create([
                    'menu' => 'main',
                    'parent_id' => $parent->id,
                    'label' => 'Account aanmaken',
                    'url' => '/'.trim(route('register', [], false), '/'),
                    'sort' => $parent->children()->count(),
                ]);
            }
        }

        $footer = $document->querySelector('footer.elementor-location-footer');
        $column = null;
        $sort = 0;

        foreach ($footer?->querySelectorAll('.elementor-widget-heading, .elementor-widget-icon-list') ?? [] as $widget) {
            if ($widget->classList->contains('elementor-widget-heading')) {
                $title = trim((string) $widget->textContent);
                $column = $title === 'OVER ONS' ? null : MenuItem::query()->create(['menu' => 'footer', 'label' => $title, 'sort' => $sort++]);

                continue;
            }

            if ($column === null) {
                $about = trim((string) $widget->textContent);
                if ($about !== '') {
                    Setting::put('footer_about', $about);
                }

                continue;
            }

            foreach ($widget->querySelectorAll('.elementor-icon-list-item') as $itemIndex => $listItem) {
                $link = $listItem->querySelector('a');
                $label = trim(preg_replace('/\s+/', ' ', (string) $listItem->textContent));
                $url = $link ? (string) $link->getAttribute('href') : null;

                if ($url && str_contains($url, 'email-protection')) {
                    $label = HtmlTransformer::decodeCloudflareEmail((string) $listItem->querySelector('[data-cfemail]')?->getAttribute('data-cfemail'));
                    $url = 'mailto:'.$label;
                    Setting::put('email', $label);
                    Setting::put('notification_email', Setting::get('notification_email') ?: $label);
                } elseif (! $url && preg_match('/^[0-9 +]{9,}$/', $label)) {
                    $url = 'tel:'.preg_replace('/\D/', '', $label);
                    Setting::put('phone', $label);
                } elseif (! $url && preg_match('/\d{4}\s?[A-Z]{2}/', $label)) {
                    Setting::put('address', $label);
                }

                MenuItem::query()->create([
                    'menu' => 'footer',
                    'parent_id' => $column->id,
                    'label' => $label,
                    'url' => $url ? $this->localUrl($url) : null,
                    'sort' => $itemIndex,
                ]);
            }
        }

        $site = json_decode($this->client->html($this->client->baseUrl().'/wp-json/'), true);
        Setting::put('site_name', html_entity_decode($site['name'] ?? Setting::get('site_name')));
        Setting::put('site_tagline', html_entity_decode($site['description'] ?? Setting::get('site_tagline')));
        Setting::put('cta_bar_text', trim((string) $document->querySelector('.mh-cta-bar__text')?->textContent) ?: Setting::get('cta_bar_text'));

        if (preg_match('/gtag\(["\']config["\'],\s*["\']((?:G|GT)-[A-Z0-9]+)["\']/', $document->saveHtml(), $analytics)) {
            Setting::put('google_analytics_id', $analytics[1]);
        }
    }

    private function importRedirects(): void
    {
        $redirects = [
            '/winkelwagen' => '/mijn-offerte/',
            '/mijn-account' => '/account/',
            '/afrekenen' => '/mijn-offerte/',
            '/checkout' => '/mijn-offerte/',
            '/wp-admin' => '/admin/',
            '/wp-login.php' => '/inloggen/',
            '/feed' => '/blog/',
            '/comments/feed' => '/blog/',
            '/product-categorie/geen-categorie' => '/winkel/',
        ];

        foreach ($redirects as $from => $to) {
            Redirect::query()->updateOrCreate(['from_path' => Redirect::normalize($from)], ['to_path' => $to, 'status_code' => 301]);
        }
    }

    private function downloadUploads(): void
    {
        $disk = Storage::disk('public');
        $downloads = [];

        foreach ($this->urls->uploads() as $url => $path) {
            $downloads[$url] = $disk->path($path);
        }

        $this->components->info('Afbeeldingen downloaden ('.count($downloads).' bestanden)');
        $bar = $this->output->createProgressBar(count($downloads));
        $result = $this->client->download($downloads, fn () => $bar->advance());
        $bar->finish();
        $this->newLine(2);

        $this->components->twoColumnDetail('Gedownload', (string) $result['downloaded']);
        $this->components->twoColumnDetail('Al aanwezig', (string) $result['skipped']);
        $this->components->twoColumnDetail('Mislukt', (string) count($result['failed']));

        foreach (array_slice($result['failed'], 0, 20) as $failed) {
            $this->components->warn($failed);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{meta_title: ?string, meta_description: ?string, og_image: ?string, noindex: bool}
     */
    private function seo(array $item): array
    {
        $yoast = $item['yoast_head_json'] ?? [];
        $ogImage = Arr::get($yoast, 'og_image.0.url');

        return [
            'meta_title' => isset($yoast['title']) ? html_entity_decode($yoast['title']) : null,
            'meta_description' => isset($yoast['description']) ? html_entity_decode($yoast['description']) : null,
            'og_image' => $ogImage ? $this->urls->uploadPath($ogImage) : null,
            'noindex' => Arr::get($yoast, 'robots.index') === 'noindex',
        ];
    }

    private function document(string $url): HTMLDocument
    {
        return $this->documents[$url] ??= HTMLDocument::createFromString($this->client->html($url), LIBXML_NOERROR);
    }

    private function plainExcerpt(string $html): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
        $text = preg_replace('/\s*(\[…\]|\[&hellip;\]|\[\.\.\.\])$/u', '', $text);

        return $text !== '' ? $text : null;
    }

    private function pathFromLink(string $link): string
    {
        return trim((string) parse_url($link, PHP_URL_PATH), '/');
    }

    private function absoluteUrl(string $path): string
    {
        return $this->client->baseUrl().'/'.($path === '' ? '' : trim($path, '/').'/');
    }

    private function localUrl(string $url): string
    {
        if (Str::startsWith($url, ['mailto:', 'tel:', '#'])) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host && ! Str::endsWith($host, $this->urls->sourceHost())) {
            return $url;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        return $path === '' ? '/' : $path;
    }
}
