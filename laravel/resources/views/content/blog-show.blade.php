@extends('storefront.layout')

@section('title', $post->title . ' | ' . config('app.name'))
@section('meta_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 155))
@section('og_title', $post->title)
@section('og_type', 'article')
@section('og_image', \App\Support\ContentAsset::blogUrl($post->featured_image))
@section('og_url', route('blog.show', $post->slug))

@section('content')
    <article class="container-lg account-page">
        <a class="storefront-text-link" href="{{ route('blog.index') }}">← All articles</a>
        <p class="small text-muted mt-4">{{ ($post->published_at ?? $post->created_at)->format('F j, Y') }}</p>
        <h1 class="display-6 fw-bold mb-4">{{ $post->title }}</h1>
        @if ($post->featured_image)
            <img class="content-hero-image mb-4" src="{{ \App\Support\ContentAsset::blogUrl($post->featured_image) }}" alt="{{ $post->title }}">
        @endif
        <div class="account-card content-article">{{ $post->content }}</div>
        @if ($relatedPosts->isNotEmpty())
            <section class="mt-5">
                <h2 class="h4 fw-bold mb-3">More from the blog</h2>
                <div class="row g-3">
                    @foreach ($relatedPosts as $related)
                        <div class="col-md-4"><a class="account-card d-block h-100 fw-bold" href="{{ route('blog.show', $related->slug) }}">{{ $related->title }}</a></div>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
@endsection
