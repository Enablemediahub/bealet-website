@extends('auth.layout')

@section('title', 'Sign in | ' . config('app.name'))

@section('auth_content')
    <span class="storefront-eyebrow storefront-eyebrow-dark">Welcome back</span>
    <h1 class="h2 fw-bold">Sign in</h1>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
            <label class="form-check-label" for="remember">Keep me signed in</label>
        </div>
        <button class="btn btn-primary rounded-pill w-100" type="submit">Sign in</button>
    </form>
    <div class="d-flex justify-content-between gap-2 mt-3 small">
        <a href="{{ route('password.request') }}">Forgot password?</a>
        <a href="{{ route('register') }}">Create an account</a>
    </div>
@endsection
