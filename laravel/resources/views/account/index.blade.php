@extends('storefront.layout')

@section('title', 'My account | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Your Bealet account</span>
        <h1 class="h2 fw-bold">Hello, {{ $user->name }}</h1>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="account-card">
                    <h2 class="h5 fw-bold">Profile information</h2>
                    <form method="POST" action="{{ route('account.profile.update') }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label" for="name">Full name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control" id="email" type="email" value="{{ $user->email }}" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required>
                        </div>
                        <button class="btn btn-primary rounded-pill" type="submit">Save profile</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="account-card">
                    <h2 class="h5 fw-bold">Change password</h2>
                    <form method="POST" action="{{ route('account.password.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label" for="current_password">Current password</label>
                            <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new_password">New password</label>
                            <input class="form-control" id="new_password" name="password" type="password" autocomplete="new-password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">Confirm new password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <button class="btn btn-outline-primary rounded-pill" type="submit">Update password</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="account-shortcuts mt-4">
            <a href="{{ route('account.orders') }}"><i class="fa-solid fa-box me-2"></i>View my orders</a>
        </div>
        <div class="account-card mt-4">
            <h2 class="h5 fw-bold">Appointment history</h2>
            @forelse ($appointments as $appointment)
                <div class="checkout-summary-line border-top">
                    <span>
                        <strong>{{ str($appointment->appointment_type)->replace('_', ' ')->title() }}</strong>
                        <span class="d-block text-muted small">{{ $appointment->appointment_date?->format('M j, Y') }} at {{ \Illuminate\Support\Carbon::parse($appointment->appointment_time)->format('g:i A') }}</span>
                    </span>
                    <span class="badge text-bg-light align-self-center">{{ str($appointment->status)->replace('_', ' ')->title() }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No appointments are linked to your account yet.</p>
            @endforelse
            <a class="btn btn-sm btn-outline-primary rounded-pill mt-3" href="{{ route('appointments.index') }}">Manage appointments</a>
        </div>
    </section>
@endsection
