<?php

namespace App\Modules\Marketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A campaign or automation email: plain personalised text plus an unsubscribe link. */
class MarketingMessage extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $text,
        public readonly string $business,
        public readonly ?string $unsubscribeUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'marketing::mail.message', text: 'marketing::mail.message-text');
    }
}
