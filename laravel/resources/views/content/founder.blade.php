@extends('storefront.layout')

@section('title', 'Our story | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Our story</span>
        <div class="row g-4 align-items-center mt-1">
            @if ($settings->get('founder_thumbnail') || $settings->get('founder_hero_image'))
                <div class="col-lg-5">
                    <img class="content-hero-image" src="{{ \App\Support\ContentAsset::url($settings->get('founder_hero_image') ?: $settings->get('founder_thumbnail')) }}" alt="{{ $settings->get('founder_name', 'Bealet founder') }}">
                </div>
            @endif
            <div class="{{ $settings->get('founder_thumbnail') || $settings->get('founder_hero_image') ? 'col-lg-7' : 'col-12' }}">
                <h1 class="display-6 fw-bold">{{ $settings->get('founder_name') ?: 'Bealet Optical Center' }}</h1>
                @if ($settings->get('founder_role'))<p class="text-primary fw-semibold">{{ $settings->get('founder_role') }}</p>@endif
                @if ($settings->get('founder_short_bio'))<p class="lead">{{ $settings->get('founder_short_bio') }}</p>@endif
                @if ($settings->get('founder_quote'))<blockquote class="account-card my-4">“{{ $settings->get('founder_quote') }}”</blockquote>@endif
                @if ($settings->get('founder_story'))<div class="content-article">{{ $settings->get('founder_story') }}</div>@endif
            </div>
        </div>
        @if ($gallery->isNotEmpty())
            <div class="row g-3 mt-4">
                @foreach ($gallery as $item)
                    <div class="col-sm-6 col-lg-4">
                        <figure class="account-card h-100">
                            <img class="content-card-image mb-3" src="{{ \App\Support\ContentAsset::url($item->image_path) }}" alt="{{ $item->item_title }}" loading="lazy">
                            <figcaption class="fw-bold">{{ $item->item_title }}</figcaption>
                            @if ($item->item_description)<p class="text-muted mb-0">{{ $item->item_description }}</p>@endif
                        </figure>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
