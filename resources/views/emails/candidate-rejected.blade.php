@extends('emails.layout')

@section('content')
    <p>Bonjour <strong>{{ $application->user->name }}</strong>,</p>
    <p>Après examen de votre dossier, le comité d'organisation UNEXE n'est malheureusement pas en mesure de retenir votre candidature pour cette édition.</p>

    @if($application->review_note)
        <div class="info-box">
            <p><strong>Motif :</strong> {{ $application->review_note }}</p>
        </div>
    @endif

    <p>Nous vous encourageons à continuer vos efforts et à postuler lors de la prochaine édition.</p>
    <p>Le Comité UNEXE</p>
@endsection