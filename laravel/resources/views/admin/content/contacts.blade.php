@extends('admin.layout')

@section('title', 'Contact messages | ' . config('app.name'))

@section('admin-content')
    <section class="container-lg account-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold mb-0">Contact messages</h1></div>
            <a class="btn btn-outline-primary rounded-pill" href="{{ route('admin.dashboard') }}">Admin home</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @forelse ($contacts as $contact)
            <article class="account-card mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ $contact->name }}</h2>
                        <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                        <p class="small text-muted mt-2 mb-0">{{ $contact->created_at?->format('M j, Y g:i A') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.content.contacts.update', $contact) }}" class="d-flex flex-wrap align-items-start gap-3">
                        @csrf
                        @method('PATCH')
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="is_read" value="1" @checked($contact->is_read)> Read</label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="replied" value="1" @checked($contact->replied)> Replied</label>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                    </form>
                </div>
                <p class="content-article mt-3 mb-0">{{ $contact->message }}</p>
            </article>
        @empty
            <div class="storefront-empty"><p>No contact messages yet.</p></div>
        @endforelse
        {{ $contacts->links() }}
    </section>
@endsection
