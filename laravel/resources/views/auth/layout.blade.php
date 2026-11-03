@extends('storefront.layout')

@section('content')
    <section class="container-lg auth-page">
        <div class="auth-card">
            @yield('auth_content')
        </div>
    </section>
@endsection
