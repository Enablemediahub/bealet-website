@extends('storefront.layout')

@section('title', 'Blog | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">From the Bealet team</span>
        <h1 class="h2 fw-bold">Stories, eyewear guides, and optical care</h1>
        @if ($settings->get('blog_hero_image'))
            <img class="content-hero-image mb-4" src="{{ \App\Support\ContentAsset::url($settings->get('blog_hero_image')) }}" alt="">
        @endif
        <div class="row g-4 mt-2">
            @forelse ($posts as $post)
                <div class="col-md-6 col-lg-4">
                    <article class="account-card h-100 d-flex flex-column">
                        @if ($post->featured_image)
                            <img class="content-card-image mb-3" src="{{ \App\Support\ContentAsset::blogUrl($post->featured_image) }}" alt="{{ $post->title }}" loading="lazy">
                        @endif
                        <p class="small text-muted mb-2">{{ ($post->published_at ?? $post->created_at)->format('M j, Y') }}</p>
                        <h2 class="h5 fw-bold">{{ $post->title }}</h2>
                        <p class="text-muted">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 150) }}</p>
                        <a class="btn btn-outline-primary rounded-pill mt-auto align-self-start" href="{{ route('blog.show', $post->slug) }}">Read article</a>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="storefront-empty"><h2 class="h5">No articles published yet</h2><p>Please check back soon.</p></div></div>
            @endforelse
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    </section>
@endsection
