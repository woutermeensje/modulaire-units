<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\TextQuoteRequest;
use App\Mail\TextQuoteSent;
use App\Models\TextQuote;
use App\Models\User;
use App\Support\RichText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * "Tekst offerte", for admins only: a quote typed by hand with a title and the rich text editor, without composing a
 * unit. The client gets a mail with the quote as a PDF, with the logo and the company details at the top.
 */
class TextQuoteController extends Controller
{
    /**
     * The form, with the quotes that were sent this way below it.
     */
    public function create(): View
    {
        return view('portal.text-quote', [
            'clients' => User::query()->where('is_admin', false)->orderBy('company_name')->orderBy('name')->get(['id', 'name', 'company_name', 'email']),
            'sentQuotes' => TextQuote::query()->with('client')->latest('sent_at')->latest('id')->paginate(20),
        ]);
    }

    /**
     * The PDF as the client would get it, without saving or sending anything.
     */
    public function preview(TextQuoteRequest $request): Response
    {
        return $this->pdfResponse($this->makeQuote($request), 'voorbeeld-offerte.pdf');
    }

    /**
     * Save the quote and mail it to the client with the PDF attached. If the mail fails, nothing is kept.
     */
    public function send(TextQuoteRequest $request): RedirectResponse
    {
        $quote = $this->makeQuote($request);

        DB::transaction(function () use ($quote): void {
            $quote->sent_at = now();
            $quote->save();

            Mail::to($quote->client)->send(new TextQuoteSent($quote, $this->render($quote)));
        });

        return redirect()->route('text-quotes.create')
            ->with('status', "\"{$quote->title}\" ({$quote->number()}) is verstuurd naar {$quote->client->email}.");
    }

    /**
     * A sent quote's PDF, as the client got it.
     */
    public function pdf(TextQuote $textQuote): Response
    {
        return $this->pdfResponse($textQuote, $textQuote->number().'.pdf');
    }

    private function makeQuote(TextQuoteRequest $request): TextQuote
    {
        $validated = $request->validated();

        $quote = new TextQuote([
            'created_by' => $request->user()->id,
            'client_id' => $validated['client_id'] ?? null,
            'title' => $validated['title'],
            'body' => RichText::clean($validated['body']),
        ]);
        $quote->setRelation('client', filled($validated['client_id'] ?? null) ? User::query()->findOrFail($validated['client_id']) : null);
        $quote->setRelation('creator', $request->user());

        return $quote;
    }

    private function render(TextQuote $quote): string
    {
        $logo = resource_path('images/quote-logo.png');

        return Pdf::loadView('pdf.text-quote', [
            'quote' => $quote,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->setPaper('a4')->output();
    }

    private function pdfResponse(TextQuote $quote, string $filename): Response
    {
        return response($this->render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
