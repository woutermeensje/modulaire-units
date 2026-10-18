<?php

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->submission->email ? [new Address($this->submission->email, (string) $this->submission->name)] : [],
            subject: $this->submission->typeLabel().' van '.$this->submission->name,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.submission-received');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $cv = $this->submission->data['cv_path'] ?? null;

        return $cv ? [Attachment::fromStorageDisk('local', $cv)] : [];
    }
}
