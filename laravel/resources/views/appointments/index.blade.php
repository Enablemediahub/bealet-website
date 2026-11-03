@extends('storefront.layout')

@section('title', 'My appointments | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Your schedule</span>
        <h1 class="h2 fw-bold mb-4">My appointments</h1>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @forelse ($appointments as $appointment)
            <div class="account-card mb-3">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h5 fw-bold">{{ $appointment->appointment_date->format('F j, Y') }}</h2>
                        <p class="mb-1">{{ \Illuminate\Support\Carbon::parse($appointment->appointment_time)->format('g:i A') }} · {{ str($appointment->appointment_type)->replace('_', ' ')->title() }}</p>
                        @if ($appointment->notes)
                            <p class="text-muted mb-0">{{ $appointment->notes }}</p>
                        @endif
                    </div>
                    <span class="badge text-bg-light">{{ str($appointment->status)->title() }}</span>
                </div>
                @if ($appointment->status === 'pending')
                    <form class="mt-3" method="POST" action="{{ route('appointments.cancel', $appointment) }}">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Cancel request</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="storefront-empty">
                <h2 class="h5">No appointments yet</h2>
                <p>Book a visit to see your schedule here.</p>
                <a href="{{ route('appointments.create') }}">Book an appointment</a>
            </div>
        @endforelse
        {{ $appointments->links() }}
    </section>
@endsection
