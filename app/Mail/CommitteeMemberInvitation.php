<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Invitation;

class CommitteeMemberInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $defaultPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎓 Invitation — Rejoindre le Comité UNEXE',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.committee-invitation',
            with: [
                'invitation'      => $this->invitation,
                'defaultPassword' => $this->defaultPassword,
                'loginUrl'        => config('app.frontend_url') . '/login?token=' . $this->invitation->token,
            ]
        );
    }
}