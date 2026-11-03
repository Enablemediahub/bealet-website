@extends('admin.layout')

@section('title', 'Staff | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Super administrator</span><h1 class="h2 fw-bold">Staff directory</h1>
        @include('admin._feedback')
        <form class="account-card mb-4" method="POST" enctype="multipart/form-data" action="{{ route('admin.site.staff.store') }}">@csrf
            <h2 class="h5">Add staff member</h2>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="staff-name-new">Name</label><input class="form-control" id="staff-name-new" name="name" required></div>
                <div class="col-md-4"><label class="form-label" for="staff-role-new">Designation</label><input class="form-control" id="staff-role-new" name="designation" required></div>
                <div class="col-md-4"><label class="form-label" for="staff-branch-new">Branch</label><select class="form-select" id="staff-branch-new" name="branch_id"><option value="">All branches</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label" for="staff-email-new">Email</label><input class="form-control" id="staff-email-new" name="email" type="email"></div>
                <div class="col-md-4"><label class="form-label" for="staff-contact-new">Contact</label><input class="form-control" id="staff-contact-new" name="contact"></div>
                <div class="col-md-4"><label class="form-label" for="staff-image-new">Portrait</label><input class="form-control" id="staff-image-new" name="thumbnail_upload" type="file" accept="image/jpeg,image/png,image/webp"></div>
                <div class="col-md-8"><label class="form-label" for="staff-bio-new">Bio</label><textarea class="form-control" id="staff-bio-new" name="bio" rows="2"></textarea></div>
                <div class="col-md-2"><label class="form-label" for="staff-sort-new">Sort</label><input class="form-control" id="staff-sort-new" name="sort_order" type="number" min="0" value="0" required></div>
                <div class="col-md-2 d-flex align-items-end"><label class="form-check-label mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> Active</label></div>
            </div><button class="btn btn-primary rounded-pill mt-3">Add staff member</button>
        </form>
        @foreach ($staff as $member)
            <form class="account-card mb-3" method="POST" enctype="multipart/form-data" action="{{ route('admin.site.staff.update', $member) }}">@csrf @method('PUT')
                <div class="d-flex align-items-center gap-3 mb-3">@if ($member->thumbnail)<img src="{{ \App\Support\ContentAsset::url($member->thumbnail) }}" alt="" width="58" height="58" class="rounded-circle object-fit-cover">@endif<strong>{{ $member->name }}</strong><span class="small text-muted">{{ $member->is_active ? 'Public' : 'Hidden' }}</span></div>
                <div class="row g-2">
                    @foreach (['name' => 'Name', 'designation' => 'Designation', 'email' => 'Email', 'contact' => 'Contact'] as $field => $label)
                        <div class="col-md-3"><label class="visually-hidden" for="staff-{{ $member->id }}-{{ $field }}">{{ $label }}</label><input class="form-control" id="staff-{{ $member->id }}-{{ $field }}" name="{{ $field }}" value="{{ $member->{$field} }}" placeholder="{{ $label }}" @required(in_array($field, ['name','designation']))></div>
                    @endforeach
                    <div class="col-md-3"><label class="visually-hidden" for="staff-{{ $member->id }}-branch">Branch</label><select class="form-select" id="staff-{{ $member->id }}-branch" name="branch_id"><option value="">All branches</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($member->branch_id === $branch->id)>{{ $branch->branch_name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="visually-hidden" for="staff-{{ $member->id }}-sort">Sort</label><input class="form-control" id="staff-{{ $member->id }}-sort" name="sort_order" type="number" min="0" value="{{ $member->sort_order }}" required></div>
                    <div class="col-md-6"><label class="visually-hidden" for="staff-{{ $member->id }}-bio">Bio</label><textarea class="form-control" id="staff-{{ $member->id }}-bio" name="bio" placeholder="Bio">{{ $member->bio }}</textarea></div>
                    <div class="col-md-4"><label class="visually-hidden" for="staff-{{ $member->id }}-image">Replace portrait</label><input class="form-control" id="staff-{{ $member->id }}-image" name="thumbnail_upload" type="file" accept="image/jpeg,image/png,image/webp"></div>
                    <div class="col-md-2 d-flex align-items-center"><label><input type="checkbox" name="is_active" value="1" @checked($member->is_active)> Active</label></div>
                    <div class="col-12"><button class="btn btn-sm btn-outline-primary">Save</button>@if ($member->is_active)<button class="btn btn-sm btn-outline-danger ms-2" form="hide-staff-{{ $member->id }}" type="submit">Hide</button>@endif</div>
                </div>
            </form>
            @if ($member->is_active)<form id="hide-staff-{{ $member->id }}" method="POST" action="{{ route('admin.site.staff.deactivate', $member) }}">@csrf @method('PATCH')</form>@endif
        @endforeach
        <div>{{ $staff->links() }}</div><a class="btn btn-link" href="{{ route('admin.dashboard') }}">Back to admin</a>
    </section>
@endsection
