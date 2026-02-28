<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class CandidateRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ Votre candidature UNEXE — Décision',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-rejected',
            with: [
                'application' => $this->application,
            ]
        );
    }
}