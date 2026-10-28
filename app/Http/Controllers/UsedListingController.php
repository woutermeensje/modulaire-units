<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsedListingResponseRequest;
use App\Models\Page;
use App\Models\UsedListing;
use App\Services\Content\ShortcodeRenderer;
use App\Services\SubmissionRecorder;
use App\Support\Seo;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Used units offered by organisations: the listing on /gebruikt/ and a detail page per unit with a response form.
 * Offering a unit happens from the customer account, see Portal\UsedListingController.
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

    /**
     * Offering units requires an account: guests are sent to the login page (with a link to register)
     * and return to the offer form in their account afterwards.
     */
    public function create(): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('my-used-listings.create');
        }

        redirect()->setIntendedUrl(route('my-used-listings.create'));

        return redirect()->route('login')
            ->with('status', 'Log in of maak gratis een account aan om je gebruikte units aan te bieden.');
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
