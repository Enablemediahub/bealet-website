@extends('storefront.layout')

@section('title', 'Checkout | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Secure checkout</span>
        <h1 class="h2 fw-bold mb-4">Delivery and payment</h1>
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('checkout.store') }}">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="account-card mb-4">
                        <h2 class="h5 fw-bold mb-3">Contact and delivery</h2>
                        <div class="mb-3">
                            <label class="form-label" for="name">Full name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name', $user?->name ?? '') }}" autocomplete="name" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email address</label>
                                <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $user?->email ?? '') }}" autocomplete="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Ghana phone number</label>
                                <input class="form-control" id="phone" name="phone" type="tel" value="{{ old('phone', $user?->phone ?? '') }}" autocomplete="tel" required>
                            </div>
                        </div>
                        <div class="mt-3 mb-3">
                            <label class="form-label" for="address">Street address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" autocomplete="street-address" required>{{ old('address') }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label" for="city">City / town</label>
                                <input class="form-control" id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="region">Region</label>
                                <select class="form-select" id="region" name="region" autocomplete="address-level1" required>
                                    <option value="">Choose a region</option>
                                    @foreach (['Ahafo', 'Ashanti', 'Bono', 'Bono East', 'Central', 'Eastern', 'Greater Accra', 'North East', 'Northern', 'Oti', 'Savannah', 'Upper East', 'Upper West', 'Volta', 'Western', 'Western North'] as $region)
                                        <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="zip">Postal code</label>
                                <input class="form-control" id="zip" name="zip" value="{{ old('zip') }}" autocomplete="postal-code">
                            </div>
                        </div>
                    </div>

                    <div class="account-card">
                        <h2 class="h5 fw-bold">Prescription details <span class="text-muted fw-normal">(optional)</span></h2>
                        <div class="form-check my-3">
                            <input class="form-check-input" id="prescription_enabled" name="prescription_enabled" type="checkbox" value="1" @checked(old('prescription_enabled'))>
                            <label class="form-check-label" for="prescription_enabled">Include my prescription with this order</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="frame_notes">Frames for these prescription details</label>
                            <input class="form-control" id="frame_notes" name="frame_notes" value="{{ old('frame_notes') }}" maxlength="255">
                        </div>
                        <div class="row g-3">
                            @foreach ([
                                'od_sphere' => 'Right sphere (OD)',
                                'od_cylinder' => 'Right cylinder (OD)',
                                'od_axis' => 'Right axis (OD)',
                                'od_add' => 'Right add (OD)',
                                'os_sphere' => 'Left sphere (OS)',
                                'os_cylinder' => 'Left cylinder (OS)',
                                'os_axis' => 'Left axis (OS)',
                                'os_add' => 'Left add (OS)',
                                'pd_far' => 'Far PD',
                                'pd_near' => 'Near PD',
                            ] as $key => $label)
                                <div class="col-6 col-md-4">
                                    <label class="form-label" for="prescription-{{ $key }}">{{ $label }}</label>
                                    <input class="form-control" id="prescription-{{ $key }}" name="prescription[{{ $key }}]" type="number" step="0.01" value="{{ old('prescription.'.$key) }}">
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="customer_notes">Additional notes</label>
                            <textarea class="form-control" id="customer_notes" name="customer_notes" rows="3" maxlength="2000">{{ old('customer_notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="account-card checkout-summary">
                        <h2 class="h5 fw-bold">Your order</h2>
                        @foreach ($items as $item)
                            <div class="checkout-summary-line">
                                <span>{{ $item->name }} <span class="text-muted">× {{ $item->quantity }}</span></span>
                                <strong>{{ \App\Support\Money::formatGhs(\App\Support\Money::toPesewas($item->price) * (int) $item->quantity) }}</strong>
                            </div>
                        @endforeach
                        <div class="checkout-summary-line mt-3 pt-3 border-top">
                            <span>Subtotal</span><strong>{{ \App\Support\Money::formatGhs($subtotalPesewas) }}</strong>
                        </div>
                        <div class="checkout-summary-line">
                            <span>VAT included</span><span>{{ \App\Support\Money::formatGhs((int) round($subtotalPesewas * 20 / 120)) }}</span>
                        </div>
                        <div class="checkout-summary-line fw-bold fs-5 mt-2">
                            <span>Total (GHS)</span><strong>{{ \App\Support\Money::formatGhs($subtotalPesewas) }}</strong>
                        </div>
                        <p class="small text-muted mt-3">Your stock is reserved for 30 minutes while payment is completed. Payment is securely processed by Paystack.</p>
                        <button class="btn btn-primary btn-lg rounded-pill w-100" type="submit">Continue to Paystack</button>
                        <a class="btn btn-link w-100 mt-2" href="{{ route('cart.index') }}">Return to cart</a>
                    </div>
                </div>
            </div>
        </form>
    </section>
@endsection
