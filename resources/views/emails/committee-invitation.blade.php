@extends('emails.layout')

@section('content')
    <p>Bonjour,</p>
    <p>Vous avez été invité(e) à rejoindre le <strong>Comité d'organisation UNEXE</strong> en tant que membre officiel.</p>

    <div class="info-box">
        <p><strong>📧 Email :</strong> {{ $invitation->email }}</p>
        <p><strong>🔑 Mot de passe provisoire :</strong> {{ $defaultPassword }}</p>
    </div>

    <p>Cliquez sur le bouton ci-dessous pour vous connecter et configurer votre compte :</p>

    <a href="{{ $loginUrl }}" class="btn">Accéder à mon espace</a>

    <p style="color: #e63946; font-size: 13px;">
        ⚠️ Ce lien expire dans 48 heures. Pensez à changer votre mot de passe après connexion.
    </p>

    <p>Bienvenue dans l'équipe UNEXE !</p>
@endsection