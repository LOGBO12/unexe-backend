@extends('emails.layout')

@section('content')
    <p>Félicitations !</p>
    <p>Vous avez été <strong>sélectionné(e) directement</strong> pour participer au concours <strong>University Excellence Elite (UNEXE)</strong>.</p>

    @if($invitation->department)
        <p>Département : <strong>{{ $invitation->department->name }}</strong></p>
    @endif

    <div class="info-box">
        <p><strong>📧 Email :</strong> {{ $invitation->email }}</p>
        <p><strong>🔑 Mot de passe provisoire :</strong> {{ $defaultPassword }}</p>
    </div>

    <p>Connectez-vous pour accéder à votre espace candidat et compléter votre profil :</p>

    <a href="{{ $loginUrl }}" class="btn">Accéder à mon espace candidat</a>

    <p style="color: #e63946; font-size: 13px;">
        ⚠️ Ce lien expire dans 48 heures.
    </p>
@endsection