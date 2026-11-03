@extends('admin.layout')

@section('title', ($post->exists ? 'Edit blog post' : 'New blog post') . ' | ' . config('app.name'))

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span>
        <h1 class="h2 fw-bold mb-4">{{ $post->exists ? 'Edit blog post' : 'Create blog post' }}</h1>
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form class="account-card" method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.content.blog.update', $post) : route('admin.content.blog.store') }}">
            @csrf
            @if ($post->exists)@method('PUT')@endif
            <div class="mb-3"><label class="form-label" for="blog-title">Title</label><input class="form-control" id="blog-title" name="title" value="{{ old('title', $post->title) }}" required maxlength="255"></div>
            <div class="mb-3"><label class="form-label" for="blog-slug">URL slug <span class="text-muted">(optional; generated from title when blank)</span></label><input class="form-control" id="blog-slug" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="255"></div>
            <div class="mb-3"><label class="form-label" for="blog-excerpt">Excerpt</label><textarea class="form-control" id="blog-excerpt" name="excerpt" rows="2" maxlength="1000">{{ old('excerpt', $post->excerpt) }}</textarea></div>
            <div class="mb-3"><label class="form-label" for="blog-image">Featured image path or URL</label><input class="form-control" id="blog-image" name="featured_image" value="{{ old('featured_image', $post->featured_image) }}" maxlength="255"></div>
            <div class="mb-3"><label class="form-label" for="blog-image-upload">Or upload a featured image</label><input class="form-control" id="blog-image-upload" name="featured_image_upload" type="file" accept="image/jpeg,image/png,image/webp"></div>
            <div class="mb-3"><label class="form-label" for="blog-content">Article content</label><textarea class="form-control" id="blog-content" name="content" rows="14" maxlength="50000" required>{{ old('content', $post->content) }}</textarea><small class="form-text text-muted">Content is rendered as escaped plain text; HTML is not executed.</small></div>
            <div class="form-check mb-3"><input class="form-check-input" id="is-published" type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))><label class="form-check-label" for="is-published">Publish this post</label></div>
            <button class="btn btn-primary rounded-pill" type="submit">Save post</button>
            <a class="btn btn-link" href="{{ route('admin.content.blog') }}">Cancel</a>
        </form>
    </section>
@endsection
