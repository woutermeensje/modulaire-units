<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Unit;
use App\Services\Content\ShortcodeRenderer;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = Str::limit(trim((string) $request->query('s', '')), 100, '');

        $units = collect();
        $otherResults = collect();

        if ($query !== '') {
            $like = '%'.$query.'%';

            $units = Unit::query()->published()->with('terms')
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('content', 'like', $like))
                ->orderBy('sort')
                ->get();

            $pages = Page::query()->published()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('content', 'like', $like))
                ->limit(20)
                ->get()
                ->map(fn (Page $page): array => [
                    'url' => $page->url(),
                    'title' => $page->title,
                    'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(ShortcodeRenderer::strip($page->content)))), 160),
                ]);

            $posts = Post::query()->published()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('content', 'like', $like))
                ->limit(20)
                ->get()
                ->map(fn (Post $post): array => [
                    'url' => $post->url(),
                    'title' => $post->title,
                    'excerpt' => $post->summary(25),
                ]);

            $otherResults = $pages->concat($posts);
        }

        $seo = Seo::make($query !== '' ? "Zoekresultaten voor “{$query}”" : 'Zoeken');
        $seo->noindex = true;

        return view('search.index', [
            'query' => $query,
            'units' => $units,
            'otherResults' => $otherResults,
            'seo' => $seo,
        ]);
    }
}
