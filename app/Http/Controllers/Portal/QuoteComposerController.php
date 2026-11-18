<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Mail\QuoteSent;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuotePdf;
use App\Support\Locale;
use App\Support\UnitConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * The quote tool, for admins only: compose a unit with everything that was taken out of the customer's composer
 * (use, connections, furniture, a step), give it a title, an accompanying text and a client, add the technical
 * specification, price lines and terms (see units.md), and send it to the client as a PDF with the drawings of the
 * unit and the list of everything ticked.
 */
class QuoteComposerController extends Controller
{
    /** The query parameter that opens the form filled in with an earlier quote, e.g. ?kopie=7. */
    public const COPY_KEY = 'kopie';

    /**
     * The price lines a new quote starts with: what every quote has to say something about, without a price.
     */
    private const STARTING_LINES = [
        'Modulaire unit',
        'Transport naar de locatie',
        'Plaatsing en kraan',
        'Fundering of ondergrond (bijv. stelconplaten)',
        'Aansluiten van stroom, water en riool op de locatie',
    ];

    public function __invoke(Request $request): View
    {
        $previous = $request->old('configuration');
        // An earlier quote as the starting point: everything is copied except the images, which the browser makes anew.
        $source = $request->integer(self::COPY_KEY) ? Quote::query()->find($request->integer(self::COPY_KEY)) : null;
        // Its validity is not copied: the new quote gets a month again.
        $draft = $source?->replicate(['images', 'sent_at', 'valid_until']) ?? new Quote([
            'kind' => 'sale',
            'lines' => array_map(fn (string $description): array => ['description' => $description, 'quantity' => 1, 'price' => null, 'period' => 'once', 'status' => 'tbd'], self::STARTING_LINES),
        ]);

        return view('portal.unit-composer', [
            'title' => 'Offerte samenstellen',
            'crumbs' => ['Account' => route('dashboard'), 'Offerte samenstellen' => null],
            'subtitle' => 'Stel een unit samen, kies de opdrachtgever en schrijf de begeleidende tekst. De opdrachtgever krijgt een mail met de offerte als PDF: de tekeningen van de unit en een lijst met alles wat je hebt aangevinkt.',
            'configuration' => match (true) {
                is_array($previous) && filled($previous['size'] ?? null) => UnitConfiguration::normalize($previous),
                $source !== null => UnitConfiguration::normalize($source->configuration),
                default => UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            },
            'draft' => $draft,
            'copiedFrom' => $source,
            'previewUrl' => route('unit-composer.preview'),
            'quoteExtras' => true,
            'sendUrl' => route('quote-composer.send'),
            'pdfPreviewUrl' => route('quote-composer.pdf-preview'),
            'clients' => User::query()->where('is_admin', false)->orderBy('company_name')->orderBy('name')->get(['id', 'name', 'company_name', 'email']),
            'locales' => [Locale::SOURCE => Locale::SOURCE_DETAILS['name']] + array_map(fn (array $details): string => $details['name'], Locale::TRANSLATIONS),
            'quoteCount' => Quote::query()->count(),
        ]);
    }

    /**
     * All sent quotes, newest first, optionally only sales or only rentals.
     */
    public function index(Request $request): View
    {
        $kind = array_key_exists((string) $request->query('soort'), Quote::KINDS) ? (string) $request->query('soort') : null;

        return view('portal.quotes', [
            'quotes' => Quote::query()
                ->with('client')
                ->when($kind, fn ($query) => $query->where('kind', $kind))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'kind' => $kind,
            'locales' => [Locale::SOURCE => Locale::SOURCE_DETAILS['name']] + array_map(fn (array $details): string => $details['name'], Locale::TRANSLATIONS),
        ]);
    }

    /**
     * The PDF as the client would get it, without saving or sending anything.
     */
    public function preview(QuoteRequest $request, QuotePdf $pdf): Response
    {
        $quote = $this->makeQuote($request);

        return response($pdf->render($quote, $pdf->dataUris($request->file('images', []))), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="voorbeeld-offerte.pdf"',
        ]);
    }

    /**
     * Save the quote with its images and mail it to the client with the PDF attached. If making the PDF or sending the
     * mail fails, nothing is kept, so the quote doesn't show up as sent.
     */
    public function send(QuoteRequest $request, QuotePdf $pdf): RedirectResponse
    {
        $quote = $this->makeQuote($request);

        try {
            DB::transaction(function () use ($quote, $request, $pdf): void {
                $quote->sent_at = now();
                $quote->save();
                $pdf->storeImages($quote, $request->file('images', []));

                Mail::to($quote->client)->locale($quote->locale)->send(new QuoteSent($quote, $pdf->render($quote)));
            });
        } catch (Throwable $exception) {
            if ($quote->id !== null) {
                Storage::disk('local')->deleteDirectory("quotes/{$quote->id}");
            }

            throw $exception;
        }

        return redirect()->route('quotes.index')
            ->with('status', "\"{$quote->title}\" ({$quote->number()}) is verstuurd naar {$quote->client->email}.");
    }

    /**
     * A sent quote's PDF, as the client got it.
     */
    public function pdf(Quote $quote, QuotePdf $pdf): Response
    {
        return response($pdf->render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$quote->number().'.pdf"',
        ]);
    }

    private function makeQuote(QuoteRequest $request): Quote
    {
        $validated = $request->validated();

        $kind = $validated['kind'] ?? 'sale';
        $isRental = $kind === 'rent';

        $quote = new Quote([
            'client_id' => $validated['client_id'],
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'locale' => $validated['locale'],
            'kind' => $kind,
            'rental_weeks' => $isRental ? ($validated['rental_weeks'] ?? null) : null,
            'reference' => $validated['reference'] ?? null,
            'delivery_address' => $validated['delivery_address'] ?? null,
            'delivery_date' => $validated['delivery_date'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'configuration' => UnitConfiguration::normalize($validated['configuration']),
            'specifications' => array_filter(array_map(fn (?string $text): string => trim((string) $text), $validated['specifications'] ?? [])) ?: null,
            'lines' => array_map(fn (array $line): array => [
                'description' => trim($line['description']),
                'quantity' => (int) ($line['quantity'] ?? 1),
                'price' => filled($line['price'] ?? null) ? (int) round(((float) $line['price']) * 100) : null,
                // A rental is charged per week, a sale only once.
                'period' => $isRental ? $line['period'] : 'once',
                'status' => $line['status'],
            ], $validated['lines'] ?? []) ?: null,
            'terms' => $validated['terms'] ?? null,
            'open_points' => $validated['open_points'] ?? null,
            'internal_notes' => $validated['internal_notes'] ?? null,
        ]);
        $quote->setRelation('client', User::query()->findOrFail($validated['client_id']));
        $quote->setRelation('creator', $request->user());

        return $quote;
    }
}
