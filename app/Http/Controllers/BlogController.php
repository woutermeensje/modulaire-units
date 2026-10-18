<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Services\Content\ShortcodeRenderer;
use App\Support\Seo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class BlogController extends Controller
{
    private const PER_PAGE = 10;

    public function index(int $page = 1): View
    {
        $blogPage = Page::query()->where('path', 'blog')->first();
        $seo = $blogPage ? Seo::for($blogPage) : Seo::make('Blog', null, route('blog.index', [], false));

        return $this->archive(Post::query(), $page, $blogPage?->title ?? 'Blog', $seo, 'blog');
    }

    public function category(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        return $this->archive(
            $category->posts()->getQuery(),
            (int) request('page', 1),
            $category->name,
            Seo::make($category->name, $category->description, $category->url()),
            'category/'.$category->slug,
        );
    }

    public function author(string $slug): View
    {
        $author = Author::query()->where('slug', $slug)->firstOrFail();

        return $this->archive(
            $author->posts()->getQuery(),
            (int) request('page', 1),
            $author->name,
            Seo::make($author->name.', auteur', $author->bio, $author->url()),
            'author/'.$author->slug,
        );
    }

    public function renderPost(Post $post): View
    {
        $relatedPosts = Post::query()->published()
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
        $posts = $query->published()->with(['author', 'category'])
            ->latest('published_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        abort_if($page > 1 && $page > $posts->lastPage(), 404);

        $posts->setPath('/'.$basePath.'/');

        if ($basePath === 'blog') {
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
                return $page <= 1 ? '/blog/' : '/blog/page/'.$page.'/';
            }
        };
    }
}
