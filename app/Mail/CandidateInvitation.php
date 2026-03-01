<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CandidateInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public string $activationUrl;
    public string $defaultPassword;

    public function __construct(
        public Invitation $invitation,
        string $defaultPassword
    ) {
        $this->defaultPassword = $defaultPassword;

        // ✅ L'URL pointe vers /invitation/{token} — pas vers /login
        $this->activationUrl = config('app.frontend_url', 'http://localhost:5173')
            . '/invitation/' . $invitation->token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🏆 Invitation UNEXE — Activez votre compte candidat',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-invitation',
        );
    }
}