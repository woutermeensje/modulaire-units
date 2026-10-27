<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsedListingRequest;
use App\Http\Requests\UsedListingResponseRequest;
use App\Models\Page;
use App\Models\UsedListing;
use App\Services\Content\ShortcodeRenderer;
use App\Services\MediaUploader;
use App\Services\SubmissionRecorder;
use App\Support\Seo;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Used units offered by visitors: the listing on /gebruikt/, a detail page per unit with a response form,
 * and the free "Units aanbieden" form. New listings are published after approval in the admin.
 */
class UsedListingController extends Controller
{
    public const PAGE_PATH = 'gebruikt';

    /**
     * Rendered by the PageController for the /gebruikt/ page, so its title, text and SEO stay editable.
     */
    public function index(Page $page): View
    {
        return view('used-listings.index', [
            'page' => $page,
            'listings' => UsedListing::query()->published()->latest('published_at')->get(),
            'content' => trim(app(ShortcodeRenderer::class)->render($page->content, $page)),
            'seo' => Seo::for($page, $page->excerpt),
        ]);
    }

    public function show(string $slug): View
    {
        $listing = UsedListing::query()->where('slug', $slug)->firstOrFail();

        abort_unless($listing->isPublished() || Auth::check(), 404);

        return view('used-listings.show', [
            'listing' => $listing,
            'seo' => new Seo(
                title: Seo::withSiteName($listing->title),
                description: $listing->summary(),
                canonical: $listing->url(),
                image: $listing->coverImage(),
            ),
        ]);
    }

    public function create(): View
    {
        return view('used-listings.create', [
            'seo' => Seo::make('Units aanbieden', 'Bied je gebruikte modulaire units gratis aan op ModulaireHuisvesting.nl.', route('used-listings.create', [], false)),
        ]);
    }

    public function store(UsedListingRequest $request, MediaUploader $uploader, SubmissionRecorder $recorder): RedirectResponse
    {
        if (! $recorder->isSpam($request)) {
            $listing = UsedListing::query()->create([
                ...$request->safe()->only(['title', 'description', 'location', 'price', 'contact_name', 'contact_email', 'contact_phone']),
                'slug' => UsedListing::uniqueSlug($request->input('title')),
                'images' => array_map(
                    fn ($file): array => ['path' => $uploader->store($file, $request->input('title')), 'alt' => null],
                    $request->file('images', []),
                ),
                'ip_address' => $request->ip(),
                'status' => 'pending',
            ]);

            $recorder->record('gebruikt_aanbod', [
                'name' => $listing->contact_name,
                'email' => $listing->contact_email,
                'phone' => $listing->contact_phone,
                'message' => $listing->description,
                'data' => [
                    'titel' => $listing->title,
                    'locatie' => $listing->location,
                    'prijs' => $listing->formattedPrice(),
                    'beoordelen' => route('admin.used-listings.edit', $listing->id),
                ],
                'source_url' => SiteUrl::absolute(SiteUrl::path(route('used-listings.create', [], false))),
            ], $request);
        }

        return redirect()->to(SiteUrl::path(route('used-listings.create', [], false)))
            ->with('form_success', 'Bedankt! Je unit is ontvangen. Na een korte controle plaatsen we de advertentie gratis op onze website.');
    }

    public function respond(UsedListingResponseRequest $request, string $slug, SubmissionRecorder $recorder): RedirectResponse
    {
        $listing = UsedListing::query()->published()->where('slug', $slug)->firstOrFail();

        if (! $recorder->isSpam($request)) {
            $recorder->record('gebruikt_reactie', [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'message' => $request->input('message'),
                'data' => array_filter([
                    'unit' => $listing->title,
                    'aanbieder' => $listing->contact_name,
                    'e-mail_aanbieder' => $listing->contact_email,
                    'telefoon_aanbieder' => $listing->contact_phone,
                ]),
                'source_url' => SiteUrl::absolute($listing->url()),
            ], $request);
        }

        return redirect()->to($listing->url().'#reageren')
            ->with('form_success', 'Bedankt voor je reactie! We brengen je zo snel mogelijk in contact met de aanbieder.');
    }
}
