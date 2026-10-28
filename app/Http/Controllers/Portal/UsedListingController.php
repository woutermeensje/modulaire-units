<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsedListingRequest;
use App\Models\UsedListing;
use App\Services\MediaUploader;
use App\Services\SubmissionRecorder;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organisations offer their used units from their account. New listings are published after approval in the admin.
 */
class UsedListingController extends Controller
{
    public function index(Request $request): View
    {
        return view('portal.used-listings.index', [
            'listings' => $request->user()->usedListings()->latest()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('portal.used-listings.create', ['user' => $request->user()]);
    }

    public function store(UsedListingRequest $request, MediaUploader $uploader, SubmissionRecorder $recorder): RedirectResponse
    {
        $user = $request->user();

        $listing = $user->usedListings()->create([
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
            'data' => array_filter([
                'organisatie' => $user->company_name,
                'account' => $user->email,
                'titel' => $listing->title,
                'locatie' => $listing->location,
                'prijs' => $listing->formattedPrice(),
                'beoordelen' => route('admin.used-listings.edit', $listing->id),
            ]),
            'source_url' => SiteUrl::absolute(route('my-used-listings.create', [], false)),
        ], $request);

        return redirect()->route('my-used-listings.index')
            ->with('status', 'Bedankt! Je unit is ontvangen. Na een korte controle plaatsen we de advertentie gratis op onze website.');
    }
}
