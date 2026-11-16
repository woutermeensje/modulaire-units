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
 * (use, connections, furniture, a step), give it a title, an accompanying text and a client, and send it to the client
 * as a PDF with the drawings of the unit and the list of everything ticked.
 */
class QuoteComposerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $previous = $request->old('configuration');

        return view('portal.unit-composer', [
            'title' => 'Offerte samenstellen',
            'crumbs' => ['Account' => route('dashboard'), 'Offerte samenstellen' => null],
            'subtitle' => 'Stel een unit samen, kies de opdrachtgever en schrijf de begeleidende tekst. De opdrachtgever krijgt een mail met de offerte als PDF: de tekeningen van de unit en een lijst met alles wat je hebt aangevinkt.',
            'configuration' => is_array($previous) && filled($previous['size'] ?? null)
                ? UnitConfiguration::normalize($previous)
                : UnitConfiguration::fromShareCode((string) $request->query(UnitConfiguration::SHARE_KEY, '')),
            'previewUrl' => route('unit-composer.preview'),
            'quoteExtras' => true,
            'sendUrl' => route('quote-composer.send'),
            'pdfPreviewUrl' => route('quote-composer.pdf-preview'),
            'clients' => User::query()->where('is_admin', false)->orderBy('company_name')->orderBy('name')->get(['id', 'name', 'company_name', 'email']),
            'locales' => [Locale::SOURCE => Locale::SOURCE_DETAILS['name']] + array_map(fn (array $details): string => $details['name'], Locale::TRANSLATIONS),
            'quotes' => Quote::query()->with('client')->latest()->limit(20)->get(),
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

        return redirect()->route('quote-composer')
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

        $quote = new Quote([
            'client_id' => $validated['client_id'],
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'locale' => $validated['locale'],
            'intro' => $validated['intro'] ?? null,
            'configuration' => UnitConfiguration::normalize($validated['configuration']),
        ]);
        $quote->setRelation('client', User::query()->findOrFail($validated['client_id']));
        $quote->setRelation('creator', $request->user());

        return $quote;
    }
}
