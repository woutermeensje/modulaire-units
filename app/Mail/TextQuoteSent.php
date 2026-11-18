<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\TextQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A quote typed by hand in /account/tekst-offerte/, to the client with the PDF attached. Replies go to the admin who
 * sent it.
 */
class TextQuoteSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TextQuote $quote, public string $pdf) {}

    public function envelope(): Envelope
    {
        $sender = $this->quote->creator;

        return new Envelope(
            replyTo: $sender ? [new Address($sender->email, (string) $sender->name)] : [],
            subject: $this->quote->title.' ('.$this->quote->number().')',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.text-quote-sent', with: ['siteName' => Setting::get('site_name')]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->quote->number().'.pdf')->withMime('application/pdf'),
        ];
    }
}
