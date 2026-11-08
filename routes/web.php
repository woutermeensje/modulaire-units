<?php

use App\Http\Controllers\Admin\UnitPreviewController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UnitComposerController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UsedListingController;
use App\Http\Controllers\VacancyController;
use App\Http\Middleware\EnsureTrailingSlash;
use App\Http\Middleware\SetLocale;
use App\Models\UnitTerm;
use App\Support\Locale;
use App\Support\SiteUrl;
use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';

/*
|--------------------------------------------------------------------------
| Machine readable files
|--------------------------------------------------------------------------
*/
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::redirect('sitemap_index.xml', '/sitemap.xml', 301);
Route::redirect('wp-sitemap.xml', '/sitemap.xml', 301);
Route::get('{sitemap}-sitemap.xml', fn () => redirect('/sitemap.xml', 301))->where('sitemap', '[a-z_]+');

/*
|--------------------------------------------------------------------------
| Old WordPress upload URLs keep working (Google Images, external links)
|--------------------------------------------------------------------------
*/
Route::get('wp-content/uploads/{path}', fn (string $path) => redirect(SiteUrl::media('media/'.$path), 301))->where('path', '.*');

/*
|--------------------------------------------------------------------------
| Public unit composer: only the free 2D preview, deliberately no AI endpoint
|--------------------------------------------------------------------------
*/
Route::post('unit-samensteller/voorbeeld', UnitPreviewController::class)->middleware('throttle:60,1')->name('unit-composer.public.preview');

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
|
| Registered once per language. The Dutch URLs are identical to the former WordPress site; the English site lives
| under /en/ with English URL segments and route names prefixed with "en." (see App\Support\SiteUrl::route()).
|
*/
$publicRoutes = function (string $locale): void {
    $segment = fn (string $dutch, string $english): string => $locale === Locale::SOURCE ? $dutch : $english;

    Route::middleware('throttle:forms')->group(function () use ($segment): void {
        Route::post($segment('offerte/toevoegen', 'quote/add'), [QuoteController::class, 'add'])->name('quote.add');
        Route::post($segment('unit-samensteller/offerte', 'unit-composer/quote'), [QuoteController::class, 'addComposition'])->name('unit-composer.quote');
        Route::post($segment('offerte/bijwerken', 'quote/update'), [QuoteController::class, 'update'])->name('quote.update');
        Route::post($segment('offerte/versturen', 'quote/submit'), [QuoteController::class, 'submit'])->name('quote.submit');
        Route::post($segment('formulier/informatie', 'form/information'), [FormController::class, 'information'])->name('forms.information');
        Route::post($segment('vacature/{slug}/solliciteren', 'vacancy/{slug}/apply'), [VacancyController::class, 'apply'])->name('vacancies.apply');
        Route::post($segment('gebruikt/aanbod/{slug}/reageren', 'used/listing/{slug}/respond'), [UsedListingController::class, 'respond'])->name('used-listings.respond');
    });

    Route::middleware(EnsureTrailingSlash::class)->group(function () use ($locale, $segment): void {
        Route::get('/', [PageController::class, 'home'])->name('home');

        Route::get($segment('mijn-offerte', 'my-quote'), [QuoteController::class, 'show'])->name('quote.show');
        Route::get($segment('zoeken', 'search'), SearchController::class)->name('search');

        Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('blog/page/{page}', [BlogController::class, 'index'])->whereNumber('page')->name('blog.page');
        Route::get('category/{slug}', [BlogController::class, 'category'])->name('blog.category');
        Route::get('author/{slug}', [BlogController::class, 'author'])->name('blog.author');

        Route::get($segment('unit-samensteller', 'unit-composer'), UnitComposerController::class)->name('unit-composer.public');

        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::get('units/{slug}', [UnitController::class, 'show'])->name('units.show');
        Route::get('{prefix}/{slug}', [UnitController::class, 'taxonomy'])
            ->where('prefix', implode('|', UnitTerm::prefixes($locale)))
            ->name('units.taxonomy');

        Route::get($segment('winkel', 'shop'), [ProductController::class, 'index'])->name('products.index');
        Route::get('product/{slug}', [ProductController::class, 'show'])->name('products.show');

        if ($locale === Locale::SOURCE) {
            Route::get('product-categorie/{slug}', fn () => redirect('/winkel/', 301));
        }

        Route::get($segment('vacature/{slug}', 'vacancy/{slug}'), [VacancyController::class, 'show'])->name('vacancies.show');

        Route::get($segment('units-aanbieden', 'offer-units'), [UsedListingController::class, 'create'])->name('used-listings.create');
        Route::get($segment('gebruikt/aanbod/{slug}', 'used/listing/{slug}'), [UsedListingController::class, 'show'])->name('used-listings.show');

        Route::get('{path}', [PageController::class, 'show'])->where('path', '.*')->name('page');
    });
};

foreach (array_keys(Locale::TRANSLATIONS) as $locale) {
    Route::prefix($locale)->name($locale.'.')->middleware(SetLocale::class.':'.$locale)->group(fn () => $publicRoutes($locale));
}

Route::middleware(SetLocale::class.':'.Locale::SOURCE)->group(fn () => $publicRoutes(Locale::SOURCE));
