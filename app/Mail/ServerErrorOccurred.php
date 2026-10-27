<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class ServerErrorOccurred extends Mailable
{
    use Queueable;

    public function __construct(
        public string $exceptionClass,
        public string $exceptionMessage,
        public string $location,
        public string $trace,
        public ?string $url = null,
        public ?string $method = null,
        public ?string $ip = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Serverfout op '.config('app.name').': '.Str::limit(class_basename($this->exceptionClass).' – '.$this->exceptionMessage, 120),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.server-error-occurred');
    }
}
