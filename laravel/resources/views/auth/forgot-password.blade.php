@extends('auth.layout')

@section('title', 'Reset password | ' . config('app.name'))

@section('auth_content')
    <span class="storefront-eyebrow storefront-eyebrow-dark">Account recovery</span>
    <h1 class="h2 fw-bold">Forgot your password?</h1>
    @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <p class="text-muted">Enter your account email and we’ll send a password reset link if the account exists.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label class="form-label" for="email">Email address</label>
        <input class="form-control mb-3" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
        <button class="btn btn-primary rounded-pill w-100" type="submit">Send reset link</button>
    </form>
    <p class="small mt-3 mb-0"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
