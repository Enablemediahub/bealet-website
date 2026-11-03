@extends('auth.layout')

@section('title', 'Create account | ' . config('app.name'))

@section('auth_content')
    <span class="storefront-eyebrow storefront-eyebrow-dark">Join Bealet</span>
    <h1 class="h2 fw-bold">Create your account</h1>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">Full name</label>
            <input class="form-control" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="phone">Ghana phone number</label>
            <input class="form-control" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" placeholder="+233 24 000 0000" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
            <div class="form-text">Use at least 8 characters with uppercase, lowercase, a number, and a symbol.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary rounded-pill w-100" type="submit">Create account</button>
    </form>
    <p class="small mt-3 mb-0">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
