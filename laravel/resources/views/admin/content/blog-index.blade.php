@extends('admin.layout')

@section('title', 'Manage blog | ' . config('app.name'))

@section('admin-content')
    <section class="container-lg account-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold mb-0">Blog posts</h1></div>
            <div class="d-flex gap-2"><a class="btn btn-outline-primary rounded-pill" href="{{ route('admin.dashboard') }}">Admin home</a><a class="btn btn-primary rounded-pill" href="{{ route('admin.content.blog.create') }}">New post</a></div>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <div class="account-card table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Title</th><th>Published</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr>
                            <td>{{ $post->title }}<span class="d-block small text-muted">/blog/{{ $post->slug }}</span></td>
                            <td><span class="badge {{ $post->is_published ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $post->is_published ? 'Published' : 'Draft' }}</span></td>
                            <td>{{ $post->updated_at?->format('M j, Y') }}</td>
                            <td class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.content.blog.edit', $post) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.content.blog.destroy', $post) }}" onsubmit="return confirm('Delete this post?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No posts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $posts->links() }}
        </div>
    </section>
@endsection
