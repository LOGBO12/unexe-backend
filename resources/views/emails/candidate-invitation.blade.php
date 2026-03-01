@extends('emails.layout')

@section('content')
    <p>Félicitations !</p>
    <p>Vous avez été <strong>sélectionné(e)</strong> pour participer au concours <strong>University Excellence Elite (UNEXE)</strong>.</p>

    @if($invitation->department)
        <p>Département : <strong>{{ $invitation->department->name }}</strong></p>
    @endif

    <div class="info-box">
        <p><strong>📧 Email :</strong> {{ $invitation->email }}</p>
        <p><strong>🔑 Mot de passe provisoire :</strong> {{ $defaultPassword }}</p>
    </div>

    <p>Cliquez sur le bouton ci-dessous pour <strong>activer votre compte</strong> et choisir votre mot de passe définitif :</p>

    {{-- ✅ $activationUrl pointe vers /invitation/{token} --}}
    <a href="{{ $activationUrl }}" class="btn">Activer mon compte candidat</a>

    <p style="color: #e63946; font-size: 13px;">
        ⚠️ Ce lien expire dans 48 heures.
    </p>
@endsection