@extends('admin.layout')

@section('title', 'Customers | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold">Customers</h1>
        <form class="row g-2 mb-3" method="GET"><div class="col-sm-9"><label class="visually-hidden" for="customer-search">Search customers</label><input class="form-control" id="customer-search" name="search" value="{{ request('search') }}" placeholder="Name, email, or phone"></div><div class="col-sm-3"><button class="btn btn-outline-primary w-100">Search</button></div></form>
        <div class="table-responsive account-card"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Contact</th><th>Orders</th><th>Joined</th><th>Account</th></tr></thead><tbody>
            @forelse ($customers as $customer)<tr><td>{{ $customer->name }}</td><td>{{ $customer->email }}<div class="small text-muted">{{ $customer->phone }}</div></td><td>{{ $customer->orders_count }}</td><td>{{ $customer->created_at?->format('M j, Y') }}</td><td>{{ $customer->is_active ? 'Active' : 'Disabled' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No customers found.</td></tr>@endforelse
        </tbody></table></div><div class="mt-3">{{ $customers->links() }}</div><a class="btn btn-link" href="{{ route('admin.dashboard') }}">Back to admin</a>
    </section>
@endsection
