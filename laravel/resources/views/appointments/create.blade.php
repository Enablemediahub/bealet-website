@extends('storefront.layout')

@section('title', 'Book an appointment | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Optical care</span>
        <h1 class="h2 fw-bold mb-4">Book an appointment</h1>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        <form class="account-card" method="POST" action="{{ route('appointments.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="appointment-name">Full name</label>
                    <input class="form-control" id="appointment-name" name="name" value="{{ old('name', auth()->user()?->name) }}" autocomplete="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="appointment-email">Email</label>
                    <input class="form-control" id="appointment-email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" autocomplete="email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="appointment-phone">Ghana phone number</label>
                    <input class="form-control" id="appointment-phone" name="phone" type="tel" value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="appointment-type">Appointment type</label>
                    <select class="form-select" id="appointment-type" name="appointment_type" required>
                        @foreach ($appointmentTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('appointment_type', 'eye_exam') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="appointment-date">Preferred date</label>
                    <input class="form-control" id="appointment-date" name="appointment_date" type="date" min="{{ now()->toDateString() }}" max="{{ now()->addDays(30)->toDateString() }}" value="{{ old('appointment_date') }}" required>
                    <small class="form-text text-muted">Appointments are available Monday through Saturday, up to 30 days ahead.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="appointment-time">Preferred time</label>
                    <select class="form-select" id="appointment-time" name="appointment_time" required>
                        <option value="">Select a time slot</option>
                        @foreach ($timeSlots as $slot)
                            <option value="{{ $slot }}" @selected(old('appointment_time') === $slot)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $slot)->format('g:i A') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="appointment-notes">Additional notes <span class="text-muted">(optional)</span></label>
                    <textarea class="form-control" id="appointment-notes" name="notes" rows="4" maxlength="2000">{{ old('notes') }}</textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary rounded-pill px-4" type="submit">Request appointment</button>
                </div>
            </div>
        </form>
    </section>
@endsection
