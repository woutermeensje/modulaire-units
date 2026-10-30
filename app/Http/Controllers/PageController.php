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
use App\Support\UnitConfiguration;
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
    public function show(Request $request, string $path = ''): View|RedirectResponse
    {
        $path = trim($path, '/');

        if ($redirect = $this->findRedirect($path)) {
            $redirect->increment('hits');

            return redirect($this->redirectTarget($request, $redirect), $redirect->status_code);
        }

        $page = Page::query()->where('path', $path)->first();

        if ($page && ($page->isPublished() || Auth::check())) {
            return $this->renderPage($page, $request);
        }

        if (! str_contains($path, '/') && $path !== '') {
            $post = Post::query()->where('slug', $path)->with(['author', 'category'])->first();

            if ($post && ($post->isPublished() || Auth::check())) {
                return app(BlogController::class)->renderPost($post);
            }
        }

        abort(404);
    }

    private function renderPage(Page $page, Request $request): View
    {
        if (OfferLanding::appliesTo($page)) {
            $landing = app(OfferLanding::class)->build($page);

            return view('pages.offer', [
                'page' => $page,
                'landing' => $landing,
                'seo' => Seo::for($page, $page->excerpt ?: $landing['body']),
                'composer' => $this->composer($request),
            ]);
        }

        if (UnitTypesOverview::appliesTo($page)) {
            return view('pages.unit-types', [
                'page' => $page,
                'overview' => app(UnitTypesOverview::class)->build($page),
                'seo' => Seo::for($page, $page->excerpt),
                'composer' => $this->composer($request),
            ]);
        }

        if ($page->path === UsedListingController::PAGE_PATH) {
            return app(UsedListingController::class)->index($page);
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
            ...($page->path === '' ? ['composer' => $this->composer($request)] : []),
        ]);
    }

    /**
     * The public unit composer on the home page, /modulaire-units/ and the huur- en koopagina's; a shared composition is read from the link, as on /unit-samensteller/.
     *
     * @return array{configuration: array<string, mixed>}
     */
    private function composer(Request $request): array
    {
        return [
            'configuration' => UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
        ];
    }

    /**
     * Absolute target URL: the URL generator would strip the trailing slash, causing a second redirect.
     */
    private function redirectTarget(Request $request, Redirect $redirect): string
    {
        if (str_starts_with($redirect->to_path, 'http://') || str_starts_with($redirect->to_path, 'https://')) {
            return $redirect->to_path;
        }

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().'/'.ltrim($redirect->to_path, '/');
    }

    private function findRedirect(string $path): ?Redirect
    {
        return Redirect::query()->where('from_path', Redirect::normalize($path))->first();
    }
}
