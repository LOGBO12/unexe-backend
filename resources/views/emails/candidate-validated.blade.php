@extends('emails.layout')

@section('content')
    <p>Félicitations <strong>{{ $application->user->name }}</strong> !</p>
    <p>Votre candidature au concours <strong>UNEXE</strong> a été <strong style="color: green;">validée</strong> par le comité d'organisation.</p>

    <div class="info-box">
        <p><strong>🏛️ Département :</strong> {{ $application->department->name }}</p>
        <p><strong>📚 Filière :</strong> {{ $application->filiere }}</p>
        <p><strong>📅 Année :</strong> {{ $application->year }}ère/ème année</p>
    </div>

    <p>Connectez-vous pour <strong>compléter votre profil</strong>. Cette étape est obligatoire pour finaliser votre participation.</p>

    <a href="{{ $loginUrl }}?for=candidat" class="btn">Accéder à mon espace candidat</a>
@endsection 