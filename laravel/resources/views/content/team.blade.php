@extends('storefront.layout')

@section('title', 'Our team | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Bealet Optical staff</span>
        <h1 class="h2 fw-bold">Meet the team behind your care</h1>
        @if ($settings->get('staff_hero_image'))
            <img class="content-hero-image my-4" src="{{ \App\Support\ContentAsset::url($settings->get('staff_hero_image')) }}" alt="">
        @endif
        <div class="row g-4 mt-2">
            @forelse ($staff as $member)
                <div class="col-md-6 col-xl-3">
                    <article class="account-card h-100">
                        @if ($member->thumbnail)
                            <img class="team-member-image mb-3" src="{{ \App\Support\ContentAsset::url($member->thumbnail) }}" alt="{{ $member->name }}" loading="lazy">
                        @endif
                        <h2 class="h5 fw-bold">{{ $member->name }}</h2>
                        <p class="text-primary fw-semibold">{{ $member->designation }}</p>
                        @if ($member->branch_name)<p class="small text-muted">{{ $member->branch_name }}</p>@endif
                        @if ($member->bio)<p>{{ $member->bio }}</p>@endif
                        @if ($member->email)<a class="d-block" href="mailto:{{ $member->email }}">{{ $member->email }}</a>@endif
                        @if ($member->contact)<a href="tel:{{ $member->contact }}">{{ $member->contact }}</a>@endif
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="storefront-empty"><p>Staff profiles will appear here soon.</p></div></div>
            @endforelse
        </div>
    </section>
@endsection
