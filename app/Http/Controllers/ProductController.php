<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use App\Services\Content\ShortcodeRenderer;
use App\Support\Seo;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $shopPage = Page::query()->where('path', 'winkel')->first();

        return view('products.index', [
            'products' => Product::query()->published()->orderBy('sort')->orderBy('title')->get(),
            'seo' => $shopPage ? Seo::for($shopPage) : Seo::make('Winkel', null, route('products.index', [], false)),
        ]);
    }

    public function show(string $slug): View
    {
        $product = Product::query()->where('slug', $slug)->firstOrFail();

        abort_unless($product->isPublished() || Auth::check(), 404);

        return view('products.show', [
            'product' => $product,
            'content' => app(ShortcodeRenderer::class)->render($product->content),
            'relatedProducts' => Product::query()->published()->whereKeyNot($product->id)->inRandomOrder()->limit(4)->get(),
            'seo' => Seo::for($product, $product->short_description ?: $product->content),
        ]);
    }
}
