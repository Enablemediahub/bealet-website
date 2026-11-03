@extends('admin.layout')

@section('title', 'Founder gallery | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Super administrator</span><h1 class="h2 fw-bold">Founder gallery</h1>
        @include('admin._feedback')
        <form class="account-card mb-4" method="POST" enctype="multipart/form-data" action="{{ route('admin.site.gallery.store') }}">@csrf
            <h2 class="h5">Add gallery item</h2><div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="gallery-title">Title</label><input class="form-control" id="gallery-title" name="item_title" required></div>
                <div class="col-md-3"><label class="form-label" for="gallery-type">Type</label><input class="form-control" id="gallery-type" name="item_type" value="portrait" required></div>
                <div class="col-md-3"><label class="form-label" for="gallery-sort">Sort order</label><input class="form-control" id="gallery-sort" name="sort_order" type="number" min="0" value="0" required></div>
                <div class="col-md-6"><label class="form-label" for="gallery-description">Description</label><textarea class="form-control" id="gallery-description" name="item_description"></textarea></div>
                <div class="col-md-4"><label class="form-label" for="gallery-image">Image</label><input class="form-control" id="gallery-image" name="image_upload" type="file" accept="image/jpeg,image/png,image/webp" required></div>
                <div class="col-md-2 d-flex align-items-end"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
            </div><button class="btn btn-primary rounded-pill mt-3">Add to gallery</button>
        </form>
        <div class="row g-3">
            @foreach ($items as $item)
                <div class="col-md-6">
                    <form class="account-card h-100" method="POST" enctype="multipart/form-data" action="{{ route('admin.site.gallery.update', $item) }}">@csrf @method('PUT')
                        <img class="content-card-image mb-3" src="{{ \App\Support\ContentAsset::url($item->image_path) }}" alt="{{ $item->item_title }}">
                        <label class="form-label" for="item-title-{{ $item->id }}">Title</label><input class="form-control mb-2" id="item-title-{{ $item->id }}" name="item_title" value="{{ $item->item_title }}" required>
                        <label class="form-label" for="item-type-{{ $item->id }}">Type</label><input class="form-control mb-2" id="item-type-{{ $item->id }}" name="item_type" value="{{ $item->item_type }}" required>
                        <label class="form-label" for="item-description-{{ $item->id }}">Description</label><textarea class="form-control mb-2" id="item-description-{{ $item->id }}" name="item_description">{{ $item->item_description }}</textarea>
                        <div class="row g-2"><div class="col-4"><label class="form-label" for="item-sort-{{ $item->id }}">Sort</label><input class="form-control" id="item-sort-{{ $item->id }}" name="sort_order" type="number" min="0" value="{{ $item->sort_order }}" required></div><div class="col-8"><label class="form-label" for="item-image-{{ $item->id }}">Replace image</label><input class="form-control" id="item-image-{{ $item->id }}" name="image_upload" type="file" accept="image/jpeg,image/png,image/webp"></div></div>
                        <label class="mt-2"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Active</label>
                        <div class="mt-2"><button class="btn btn-sm btn-outline-primary">Save</button>@if ($item->is_active)<button class="btn btn-sm btn-outline-danger ms-2" form="hide-gallery-{{ $item->id }}" type="submit">Hide</button>@endif</div>
                    </form>
                    @if ($item->is_active)<form id="hide-gallery-{{ $item->id }}" method="POST" action="{{ route('admin.site.gallery.deactivate', $item) }}">@csrf @method('PATCH')</form>@endif
                </div>
            @endforeach
        </div><div class="mt-3">{{ $items->links() }}</div><a class="btn btn-link" href="{{ route('admin.dashboard') }}">Back to admin</a>
    </section>
@endsection
