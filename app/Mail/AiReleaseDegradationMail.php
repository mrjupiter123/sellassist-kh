<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiReleaseDegradationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param list<string> $reasons */
    public function __construct(
        public readonly string $profileName,
        public readonly string $profileVersion,
        public readonly string $releaseUuid,
        public readonly array $reasons,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SellAssist KH: AI release needs review');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ai-release-degradation');
    }
}
