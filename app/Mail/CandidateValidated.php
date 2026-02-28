<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class CandidateValidated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Votre candidature UNEXE a été validée !',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-validated',
            with: [
                'application' => $this->application,
                'loginUrl'    => config('app.frontend_url') . '/login',
            ]
        );
    }
}