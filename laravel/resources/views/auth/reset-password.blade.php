@extends('auth.layout')

@section('title', 'Choose a new password | ' . config('app.name'))

@section('auth_content')
    <span class="storefront-eyebrow storefront-eyebrow-dark">Account recovery</span>
    <h1 class="h2 fw-bold">Choose a new password</h1>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $email) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">New password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirm new password</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary rounded-pill w-100" type="submit">Reset password</button>
    </form>
@endsection
