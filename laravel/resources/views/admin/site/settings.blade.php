@extends('admin.layout')

@section('title', 'Site settings | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Super administrator</span><h1 class="h2 fw-bold">Site settings & branches</h1>
        @include('admin._feedback')
        <form class="account-card mb-4" method="POST" enctype="multipart/form-data" action="{{ route('admin.site.settings.update') }}">
            @csrf @method('PUT')
            <h2 class="h4">Brand and public content</h2>
            <div class="row g-3">
                @foreach ([
                    'company_name' => 'Company name',
                    'tagline' => 'Tagline',
                    'primary_phone' => 'Primary phone',
                    'secondary_phone' => 'Secondary phone',
                    'whatsapp_phone' => 'WhatsApp phone',
                    'email' => 'Contact email',
                    'homepage_hero_title' => 'Homepage hero title',
                    'homepage_hero_subtitle' => 'Homepage hero subtitle',
                    'founder_name' => 'Founder name',
                    'founder_role' => 'Founder role',
                    'founder_short_bio' => 'Founder short bio',
                    'founder_story' => 'Founder story',
                    'founder_quote' => 'Founder quote',
                    'facebook_url' => 'Facebook URL',
                    'instagram_url' => 'Instagram URL',
                    'twitter_url' => 'X / Twitter URL',
                    'linkedin_url' => 'LinkedIn URL',
                    'tiktok_url' => 'TikTok URL',
                ] as $key => $label)
                    <div class="{{ in_array($key, ['homepage_hero_subtitle','founder_short_bio','founder_story','founder_quote']) ? 'col-12' : 'col-md-6' }}">
                        <label class="form-label" for="setting-{{ $key }}">{{ $label }}</label>
                        @if (in_array($key, ['homepage_hero_subtitle','founder_short_bio','founder_story','founder_quote']))
                            <textarea class="form-control" id="setting-{{ $key }}" name="{{ $key }}" rows="{{ $key === 'founder_story' ? 5 : 2 }}">{{ old($key, $settings[$key] ?? '') }}</textarea>
                        @else
                            <input class="form-control" id="setting-{{ $key }}" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? '') }}">
                        @endif
                    </div>
                @endforeach
                @foreach (['logo_path' => 'Logo', 'home_hero_image' => 'Homepage hero image', 'founder_thumbnail' => 'Founder portrait', 'founder_hero_image' => 'Founder page banner', 'staff_hero_image' => 'Team page banner', 'contact_hero_image' => 'Contact page banner', 'blog_hero_image' => 'Blog page banner'] as $key => $label)
                    <div class="col-md-6">
                        <label class="form-label" for="upload-{{ $key }}">{{ $label }} (JPG, PNG, WebP)</label>
                        <input class="form-control" id="upload-{{ $key }}" name="{{ $key }}_upload" type="file" accept="image/jpeg,image/png,image/webp">
                        @if (!empty($settings[$key]))<a class="small" href="{{ \App\Support\ContentAsset::url($settings[$key]) }}" target="_blank" rel="noopener">View current image</a>@endif
                    </div>
                @endforeach
            </div>
            <button class="btn btn-primary rounded-pill mt-4" type="submit">Save settings</button>
        </form>

        <h2 class="h4">Branches</h2>
        <form class="account-card mb-3" method="POST" action="{{ route('admin.site.branches.store') }}">@csrf
            <h3 class="h6">Add branch</h3>
            <div class="row g-2">
                <div class="col-md-4"><label class="visually-hidden" for="new-branch-name">Branch name</label><input class="form-control" id="new-branch-name" name="branch_name" placeholder="Branch name" required></div>
                <div class="col-md-4"><label class="visually-hidden" for="new-branch-phone">Phone</label><input class="form-control" id="new-branch-phone" name="phone_primary" placeholder="Phone"></div>
                <div class="col-md-4"><label class="visually-hidden" for="new-branch-sort">Sort order</label><input class="form-control" id="new-branch-sort" name="sort_order" type="number" min="0" value="0" required></div>
                <div class="col-md-6"><label class="visually-hidden" for="new-branch-address">Address</label><input class="form-control" id="new-branch-address" name="address" placeholder="Address"></div>
                <div class="col-md-6"><label class="visually-hidden" for="new-branch-map">Map URL</label><input class="form-control" id="new-branch-map" name="google_maps_url" type="url" placeholder="Google Maps URL"></div>
                <div class="col-12"><label><input type="checkbox" name="is_active" value="1" checked> Active</label><button class="btn btn-outline-primary btn-sm ms-3">Add branch</button></div>
            </div>
        </form>
        @foreach ($branches as $branch)
            <form class="account-card mb-3" method="POST" action="{{ route('admin.site.branches.update', $branch) }}">@csrf @method('PUT')
                <div class="d-flex justify-content-between gap-2"><h3 class="h6">{{ $branch->branch_name }}</h3><span class="small text-muted">{{ $branch->is_active ? 'Public' : 'Hidden' }}</span></div>
                <div class="row g-2">
                    <div class="col-md-4"><label class="visually-hidden" for="branch-name-{{ $branch->id }}">Branch name</label><input class="form-control" id="branch-name-{{ $branch->id }}" name="branch_name" value="{{ $branch->branch_name }}" required></div>
                    <div class="col-md-4"><label class="visually-hidden" for="branch-phone-{{ $branch->id }}">Phone</label><input class="form-control" id="branch-phone-{{ $branch->id }}" name="phone_primary" value="{{ $branch->phone_primary }}"></div>
                    <div class="col-md-4"><label class="visually-hidden" for="branch-phone-secondary-{{ $branch->id }}">Secondary phone</label><input class="form-control" id="branch-phone-secondary-{{ $branch->id }}" name="phone_secondary" value="{{ $branch->phone_secondary }}"></div>
                    <div class="col-md-6"><label class="visually-hidden" for="branch-address-{{ $branch->id }}">Address</label><textarea class="form-control" id="branch-address-{{ $branch->id }}" name="address">{{ $branch->address }}</textarea></div>
                    <div class="col-md-4"><label class="visually-hidden" for="branch-map-{{ $branch->id }}">Map URL</label><input class="form-control" id="branch-map-{{ $branch->id }}" name="google_maps_url" type="url" value="{{ $branch->google_maps_url }}"></div>
                    <div class="col-md-2"><label class="visually-hidden" for="branch-sort-{{ $branch->id }}">Sort order</label><input class="form-control" id="branch-sort-{{ $branch->id }}" name="sort_order" type="number" min="0" value="{{ $branch->sort_order }}" required></div>
                    <div class="col-12"><label><input type="checkbox" name="is_active" value="1" @checked($branch->is_active)> Active</label><button class="btn btn-outline-primary btn-sm ms-3">Save</button>
                        @if ($branch->is_active)<button class="btn btn-outline-danger btn-sm ms-2" form="deactivate-branch-{{ $branch->id }}" type="submit">Hide</button>@endif
                    </div>
                </div>
            </form>
            @if ($branch->is_active)<form id="deactivate-branch-{{ $branch->id }}" method="POST" action="{{ route('admin.site.branches.deactivate', $branch) }}">@csrf @method('PATCH')</form>@endif
        @endforeach
        <a class="btn btn-link" href="{{ route('admin.dashboard') }}">Back to admin</a>
    </section>
@endsection
