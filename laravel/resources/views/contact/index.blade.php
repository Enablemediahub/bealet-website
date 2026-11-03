@extends('storefront.layout')

@section('title', 'Contact us | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">We are here to help</span>
        <h1 class="h2 fw-bold mb-4">Contact Bealet</h1>
        @if ($settings->get('contact_hero_image'))
            <img class="content-hero-image mb-4" src="{{ \App\Support\ContentAsset::url($settings->get('contact_hero_image')) }}" alt="">
        @endif
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <form class="account-card" method="POST" action="{{ route('contact.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="contact-name">Name</label>
                        <input class="form-control" id="contact-name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contact-email">Email</label>
                        <input class="form-control" id="contact-email" name="email" type="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contact-message">Message</label>
                        <textarea class="form-control" id="contact-message" name="message" rows="6" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4" type="submit">Send message</button>
                </form>
            </div>
            <div class="col-lg-5">
                @if ($settings->get('email'))
                    <div class="account-card mb-3"><strong>Email</strong><br><a href="mailto:{{ $settings->get('email') }}">{{ $settings->get('email') }}</a></div>
                @endif
                @foreach (['primary_phone' => 'Call us', 'secondary_phone' => 'Also available'] as $key => $label)
                    @if ($settings->get($key))
                        <div class="account-card mb-3"><strong>{{ $label }}</strong><br><a href="tel:{{ $settings->get($key) }}">{{ $settings->get($key) }}</a></div>
                    @endif
                @endforeach
                @foreach ($branches as $branch)
                    <div class="account-card mb-3">
                        <h2 class="h6 fw-bold">{{ $branch->branch_name }}</h2>
                        @if ($branch->address)<p class="mb-1">{{ $branch->address }}</p>@endif
                        @if ($branch->phone_primary)<a href="tel:{{ $branch->phone_primary }}">{{ $branch->phone_primary }}</a>@endif
                        @if ($branch->google_maps_url)<p class="mt-2 mb-0"><a href="{{ $branch->google_maps_url }}" target="_blank" rel="noopener noreferrer">Open map</a></p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
