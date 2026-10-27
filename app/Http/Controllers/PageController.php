<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Services\Content\OfferLanding;
use App\Services\Content\ServiceLanding;
use App\Services\Content\ShortcodeRenderer;
use App\Services\Content\UnitTypesOverview;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private ShortcodeRenderer $shortcodes) {}

    public function home(Request $request): View|RedirectResponse
    {
        if ($request->filled('s')) {
            return redirect()->route('search', ['s' => $request->query('s')]);
        }

        return $this->show($request, '');
    }

    /**
     * Resolve any other URL: configured redirects first, then pages by full path, then blog posts by slug.
     */
    public function show(Request $request, string $path): View|RedirectResponse
    {
        $path = trim($path, '/');

        if ($redirect = $this->findRedirect($path)) {
            $redirect->increment('hits');

            return redirect($redirect->to_path, $redirect->status_code);
        }

        $page = Page::query()->where('path', $path)->first();

        if ($page && ($page->isPublished() || Auth::check())) {
            return $this->renderPage($page);
        }

        if (! str_contains($path, '/') && $path !== '') {
            $post = Post::query()->where('slug', $path)->with(['author', 'category'])->first();

            if ($post && ($post->isPublished() || Auth::check())) {
                return app(BlogController::class)->renderPost($post);
            }
        }

        abort(404);
    }

    private function renderPage(Page $page): View
    {
        if (OfferLanding::appliesTo($page)) {
            return view('pages.offer', [
                'page' => $page,
                'landing' => app(OfferLanding::class)->build($page),
                'seo' => Seo::for($page, $page->excerpt),
            ]);
        }

        if (UnitTypesOverview::appliesTo($page)) {
            return view('pages.unit-types', [
                'page' => $page,
                'overview' => app(UnitTypesOverview::class)->build($page),
                'seo' => Seo::for($page, $page->excerpt),
            ]);
        }

        if ($page->template === 'service') {
            return view('pages.service', [
                'page' => $page,
                'service' => app(ServiceLanding::class)->build($page),
                'seo' => Seo::for($page, $page->excerpt),
            ]);
        }

        return view('pages.show', [
            'page' => $page,
            'content' => $this->shortcodes->render($page->content, $page),
            'seo' => Seo::for($page, $page->excerpt),
        ]);
    }

    private function findRedirect(string $path): ?Redirect
    {
        return Redirect::query()->where('from_path', Redirect::normalize($path))->first();
    }
}
