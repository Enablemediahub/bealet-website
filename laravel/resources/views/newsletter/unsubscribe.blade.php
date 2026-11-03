@extends('storefront.layout')

@section('title', 'Unsubscribe | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <div class="auth-card">
            <h1 class="h3 fw-bold">Unsubscribe from updates?</h1>
            <p class="text-muted">This will stop newsletter messages sent to {{ $subscriber->email }}.</p>
            <form method="POST" action="{{ route('newsletter.unsubscribe.confirm', $subscriber->unsubscribe_token) }}">
                @csrf
                <button class="btn btn-outline-danger rounded-pill" type="submit">Unsubscribe</button>
                <a class="btn btn-link" href="{{ route('home') }}">Keep me subscribed</a>
            </form>
        </div>
    </section>
@endsection
