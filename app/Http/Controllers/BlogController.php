<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Services\Content\ShortcodeRenderer;
use App\Support\Seo;
use App\Support\SiteUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class BlogController extends Controller
{
    private const PER_PAGE = 10;

    public function index(int $page = 1): View
    {
        $blogPage = Page::query()->where('path', 'blog')->first();
        $seo = $blogPage ? Seo::for($blogPage) : Seo::route('Blog', null, 'blog.index');

        return $this->archive(Post::query(), $page, $blogPage?->title ?? 'Blog', $seo, SiteUrl::route('blog.index'));
    }

    public function category(string $slug): View
    {
        $category = Category::query()->whereLocalized('slug', $slug)->firstOrFail();

        return $this->archive(
            $category->posts()->getQuery(),
            (int) request('page', 1),
            $category->name,
            Seo::make($category->name, $category->description ?: __('Alle blogartikelen in de categorie :name: tips, regels en achtergronden over modulaire units en tijdelijke huisvesting.', ['name' => $category->name]), $category->url())
                ->withAlternates(fn (): string => $category->url(), $category),
            $category->url(),
        );
    }

    public function author(string $slug): View
    {
        $author = Author::query()->whereLocalized('slug', $slug)->firstOrFail();

        return $this->archive(
            $author->posts()->getQuery(),
            (int) request('page', 1),
            $author->name,
            Seo::make(__(':name, auteur', ['name' => $author->name]), $author->bio ?: __('Lees alle blogartikelen van :name over modulaire units, tijdelijke huisvesting en de regels die daarbij horen.', ['name' => $author->name]), $author->url())
                ->withAlternates(fn (): string => $author->url(), $author),
            $author->url(),
        );
    }

    public function renderPost(Post $post): View
    {
        $relatedPosts = Post::query()->published()->translated()
            ->whereKeyNot($post->id)
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', [
            'post' => $post,
            'content' => app(ShortcodeRenderer::class)->render($post->content),
            'relatedPosts' => $relatedPosts,
            'seo' => Seo::for($post, $post->summary(30)),
        ]);
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function archive(Builder $query, int $page, string $title, Seo $seo, string $basePath): View
    {
        /** @var LengthAwarePaginator $posts */
        $posts = $query->published()->translated()->with(['author', 'category'])
            ->latest('published_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        abort_if($page > 1 && $page > $posts->lastPage(), 404);

        $posts->setPath($basePath);

        if ($basePath === SiteUrl::route('blog.index')) {
            $posts = $this->wordpressPagination($posts);
        }

        return view('blog.index', [
            'posts' => $posts,
            'archiveTitle' => $title,
            'seo' => $seo,
        ]);
    }

    /**
     * Make pagination links follow the WordPress style "/blog/page/2/".
     */
    private function wordpressPagination(LengthAwarePaginator $posts): LengthAwarePaginator
    {
        return new class($posts->items(), $posts->total(), $posts->perPage(), $posts->currentPage()) extends \Illuminate\Pagination\LengthAwarePaginator
        {
            public function url($page): string
            {
                return $page <= 1 ? SiteUrl::route('blog.index') : SiteUrl::route('blog.page', $page);
            }
        };
    }
}
