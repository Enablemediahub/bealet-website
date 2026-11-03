@extends('admin.layout')

@section('title', 'Manage appointments | ' . config('app.name'))

@section('admin-content')
    <section class="container-lg account-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold mb-0">Appointments</h1></div>
            <a class="btn btn-outline-primary rounded-pill" href="{{ route('admin.dashboard') }}">Admin home</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <div class="account-card table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Customer</th><th>Requested time</th><th>Type / notes</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->name }}<span class="d-block small text-muted">{{ $appointment->email }} · {{ $appointment->phone }}</span></td>
                            <td>{{ $appointment->appointment_date->format('M j, Y') }}<span class="d-block small text-muted">{{ \Illuminate\Support\Carbon::parse($appointment->appointment_time)->format('g:i A') }}</span></td>
                            <td>{{ str($appointment->appointment_type)->replace('_', ' ')->title() }}<span class="d-block small text-muted">{{ $appointment->notes }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('admin.content.appointments.update', $appointment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="visually-hidden" for="appointment-status-{{ $appointment->id }}">Appointment status</label>
                                    <select class="form-select form-select-sm" id="appointment-status-{{ $appointment->id }}" name="status" onchange="this.form.submit()">
                                        @foreach (['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as $status)
                                            <option value="{{ $status }}" @selected($appointment->status === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No appointment requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $appointments->links() }}
        </div>
    </section>
@endsection
