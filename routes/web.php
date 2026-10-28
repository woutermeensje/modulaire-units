<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UsedListingController;
use App\Http\Controllers\VacancyController;
use App\Http\Middleware\EnsureTrailingSlash;
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
| Form posts
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:forms')->group(function (): void {
    Route::post('offerte/toevoegen', [QuoteController::class, 'add'])->name('quote.add');
    Route::post('offerte/bijwerken', [QuoteController::class, 'update'])->name('quote.update');
    Route::post('offerte/versturen', [QuoteController::class, 'submit'])->name('quote.submit');
    Route::post('formulier/informatie', [FormController::class, 'information'])->name('forms.information');
    Route::post('vacature/{slug}/solliciteren', [VacancyController::class, 'apply'])->name('vacancies.apply');
    Route::post('gebruikt/aanbod/{slug}/reageren', [UsedListingController::class, 'respond'])->name('used-listings.respond');
});

/*
|--------------------------------------------------------------------------
| Public website — URLs are identical to the former WordPress site
|--------------------------------------------------------------------------
*/
Route::middleware(EnsureTrailingSlash::class)->group(function (): void {
    Route::get('/', [PageController::class, 'home'])->name('home');

    Route::get('mijn-offerte', [QuoteController::class, 'show'])->name('quote.show');
    Route::get('zoeken', SearchController::class)->name('search');

    Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('blog/page/{page}', [BlogController::class, 'index'])->whereNumber('page')->name('blog.page');
    Route::get('category/{slug}', [BlogController::class, 'category'])->name('blog.category');
    Route::get('author/{slug}', [BlogController::class, 'author'])->name('blog.author');

    Route::get('units', [UnitController::class, 'index'])->name('units.index');
    Route::get('units/{slug}', [UnitController::class, 'show'])->name('units.show');
    Route::get('{prefix}/{slug}', [UnitController::class, 'taxonomy'])
        ->where('prefix', 'unit-type|conditie|aanbod')
        ->name('units.taxonomy');

    Route::get('winkel', [ProductController::class, 'index'])->name('products.index');
    Route::get('product/{slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('product-categorie/{slug}', fn () => redirect('/winkel/', 301));

    Route::get('vacature/{slug}', [VacancyController::class, 'show'])->name('vacancies.show');

    Route::get('units-aanbieden', [UsedListingController::class, 'create'])->name('used-listings.create');
    Route::get('gebruikt/aanbod/{slug}', [UsedListingController::class, 'show'])->name('used-listings.show');

    Route::get('{path}', [PageController::class, 'show'])->where('path', '.*')->name('page');
});
