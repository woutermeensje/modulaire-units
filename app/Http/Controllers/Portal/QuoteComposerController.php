<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\DraftQuoteRequest;
use App\Http\Requests\QuoteRequest;
use App\Mail\QuoteSent;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuotePdf;
use App\Support\Locale;
use App\Support\QuoteSpecification;
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

    /** The query parameter that opens a saved draft to work on, e.g. ?concept=7. */
    public const DRAFT_KEY = 'concept';

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
        // A saved draft is opened as it is; saving or sending it again updates that same quote.
        $savedDraft = $request->integer(self::DRAFT_KEY) ? Quote::query()->whereNull('sent_at')->findOrFail($request->integer(self::DRAFT_KEY)) : null;
        // An earlier quote as the starting point: everything is copied except the images, which the browser makes anew.
        $source = $savedDraft ?? ($request->integer(self::COPY_KEY) ? Quote::query()->find($request->integer(self::COPY_KEY)) : null);
        $draft = $savedDraft ?? $source?->replicate(['images', 'sent_at']) ?? new Quote([
            'kind' => 'sale',
            'specifications' => QuoteSpecification::STANDARD,
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
            'savedDraft' => $savedDraft,
            'copiedFrom' => $savedDraft ? null : $source,
            'draftUrl' => route('quote-composer.draft'),
            'previewUrl' => route('unit-composer.preview'),
            'quoteExtras' => true,
            'sendUrl' => route('quote-composer.send'),
            'pdfPreviewUrl' => route('quote-composer.pdf-preview'),
            'clients' => User::query()->where('is_admin', false)->orderBy('company_name')->orderBy('name')->get(['id', 'name', 'company_name', 'email']),
            'locales' => [Locale::SOURCE => Locale::SOURCE_DETAILS['name']] + array_map(fn (array $details): string => $details['name'], Locale::TRANSLATIONS),
            'quoteCount' => Quote::query()->whereNotNull('sent_at')->count(),
            'draftCount' => Quote::query()->whereNull('sent_at')->count(),
        ]);
    }

    /**
     * All sent quotes, newest first, or the drafts (?status=concept), last edited first; optionally only sales or only
     * rentals.
     */
    public function index(Request $request): View
    {
        $kind = array_key_exists((string) $request->query('soort'), Quote::KINDS) ? (string) $request->query('soort') : null;
        $drafts = $request->query('status') === self::DRAFT_KEY;

        return view('portal.quotes', [
            'quotes' => Quote::query()
                ->with('client')
                ->when($drafts, fn ($query) => $query->whereNull('sent_at')->latest('updated_at'), fn ($query) => $query->whereNotNull('sent_at')->latest('sent_at'))
                ->when($kind, fn ($query) => $query->where('kind', $kind))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'kind' => $kind,
            'drafts' => $drafts,
            'draftCount' => Quote::query()->whereNull('sent_at')->count(),
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
        $quote = $this->makeQuote($request, $this->savedDraft($request));

        try {
            DB::transaction(function () use ($quote, $request, $pdf): void {
                $quote->sent_at = now();
                $quote->save();
                $pdf->storeImages($quote, $request->file('images', []));

                Mail::to($quote->client)->locale($quote->locale)->send(new QuoteSent($quote, $pdf->render($quote)));
            });
        } catch (Throwable $exception) {
            // A draft that was opened has no images of its own yet, so this only removes what this attempt stored.
            if ($quote->id !== null) {
                Storage::disk('local')->deleteDirectory("quotes/{$quote->id}");
            }

            throw $exception;
        }

        return redirect()->route('quotes.index')
            ->with('status', "\"{$quote->title}\" ({$quote->number()}) is verstuurd naar {$quote->client->email}.");
    }

    /**
     * Save the quote as a draft, without images and without sending anything. A draft that was opened is updated.
     */
    public function saveDraft(DraftQuoteRequest $request): RedirectResponse
    {
        $quote = $this->makeQuote($request, $this->savedDraft($request));
        $quote->save();

        return redirect()->route('quote-composer', [self::DRAFT_KEY => $quote->id])
            ->with('status', "Concept \"{$quote->title}\" ({$quote->number()}) is opgeslagen.");
    }

    /**
     * Delete a draft. A sent quote stays: the client has it.
     */
    public function destroyDraft(Quote $quote): RedirectResponse
    {
        abort_if($quote->sent_at !== null, 403, 'Een verstuurde offerte kan niet worden verwijderd.');

        $quote->delete();

        return redirect()->route('quotes.index', ['status' => self::DRAFT_KEY])
            ->with('status', "Concept \"{$quote->title}\" is verwijderd.");
    }

    /**
     * A sent quote's PDF, as the client got it.
     */
    public function pdf(Quote $quote, QuotePdf $pdf): Response
    {
        // A draft has no PDF of its own yet; "Voorbeeld PDF" in the quote composer shows it.
        abort_if($quote->sent_at === null, 404);

        return response($pdf->render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$quote->number().'.pdf"',
        ]);
    }

    private function savedDraft(QuoteRequest $request): ?Quote
    {
        $id = $request->validated('draft_id');

        return $id ? Quote::query()->whereNull('sent_at')->find($id) : null;
    }

    /**
     * The quote from the form: a new one, or the opened draft filled in again.
     */
    private function makeQuote(QuoteRequest $request, ?Quote $draft = null): Quote
    {
        $validated = $request->validated();

        $kind = $validated['kind'] ?? 'sale';
        $isRental = $kind === 'rent';

        $quote = $draft ?? new Quote(['created_by' => $request->user()->id]);
        $quote->fill([
            'client_id' => $validated['client_id'] ?? null,
            'title' => $validated['title'],
            'locale' => $validated['locale'],
            'kind' => $kind,
            'reference' => $validated['reference'] ?? null,
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
        $quote->setRelation('client', filled($validated['client_id'] ?? null) ? User::query()->findOrFail($validated['client_id']) : null);
        $quote->setRelation('creator', $draft?->creator ?? $request->user());

        return $quote;
    }
}
