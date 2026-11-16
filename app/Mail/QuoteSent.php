<?php

namespace App\Mail;

use App\Models\Quote;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A quote from /account/offerte-samenstellen/ to the client, with the accompanying text and the PDF attached.
 * Replies go to the admin who sent it.
 */
class QuoteSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote, public string $pdf) {}

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
        return new Content(markdown: 'mail.quote-sent', with: ['siteName' => Setting::get('site_name')]);
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
