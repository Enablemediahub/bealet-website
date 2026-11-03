@extends('storefront.layout')

@section('title', 'Virtual Try-On | ' . config('app.name'))

@section('meta_description', 'Try selected eyewear frames in your browser using your camera. Camera processing stays on your device.')

@section('content')
    <section class="container-lg storefront-section tryon-page">
        <div class="storefront-section-heading tryon-page-heading">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Virtual fitting room</span><h1>Try on a frame</h1></div>
            <span class="small text-muted">Camera images are processed in this browser and are not uploaded.</span>
        </div>
        @if ($frames->isEmpty())
            <div class="storefront-empty"><i class="fa-regular fa-eye"></i><p>No frames are configured for try-on yet.</p><a href="{{ route('shop.index') }}">Browse the collection</a></div>
        @else
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="tryon-stage account-card" id="tryon-stage" aria-label="Virtual try-on camera preview">
                        <video id="tryon-video" autoplay muted playsinline aria-hidden="true"></video>
                        <canvas id="tryon-canvas" aria-label="Camera preview with selected frame overlay"></canvas>
                        <model-viewer id="tryon-model" alt="Selected 3D glasses model" disable-zoom interaction-prompt="none" environment-image="neutral" shadow-intensity="0" exposure="1" style="display:none"></model-viewer>
                        <div class="tryon-status" id="tryon-status" role="status" aria-live="polite">Choose a frame, then enable your camera to begin.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-primary rounded-pill" type="button" id="tryon-start">Enable camera</button>
                        <button class="btn btn-outline-primary rounded-pill" type="button" id="tryon-flip" disabled>Switch camera</button>
                        <button class="btn btn-outline-primary rounded-pill" type="button" id="tryon-capture" disabled>Download preview</button>
                        <label class="visually-hidden" for="tryon-camera">Camera</label><select class="form-select w-auto" id="tryon-camera" aria-label="Select camera" disabled><option value="">Default camera</option></select>
                    </div>
                    <div class="tryon-zoom-control mt-3">
                        <label class="form-label fw-semibold mb-1" for="tryon-zoom">Frame size <output id="tryon-zoom-value" for="tryon-zoom">100%</output></label>
                        <input class="form-range" id="tryon-zoom" type="range" min="0.6" max="2" step="0.05" value="1" aria-describedby="tryon-zoom-help">
                        <p class="small text-muted mb-0" id="tryon-zoom-help">Once your face is detected, drag the preview to reposition the frame. Pinch or scroll to zoom.</p>
                    </div>
                    <p class="small text-muted mt-2">Use a well-lit, centered face for best tracking. Camera access requires your permission and a secure connection (HTTPS or localhost).</p>
                </div>
                <div class="col-lg-4">
                    <div class="account-card">
                        <label class="form-label fw-semibold" for="tryon-frame">Choose a frame</label>
                        <select class="form-select mb-3" id="tryon-frame">
                            @foreach ($frames as $index => $frame)
                                <option value="{{ $index }}" @selected($selectedFrame === $index)>{{ $frame['name'] }}{{ $frame['brand'] ? ' · '.$frame['brand'] : '' }}</option>
                            @endforeach
                        </select>
                        <img id="tryon-product-image" class="tryon-product-image" src="{{ $frames[$selectedFrame]['image'] }}" alt="{{ $frames[$selectedFrame]['name'] }}">
                        <h2 class="h5 mt-3 mb-1" id="tryon-product-name">{{ $frames[$selectedFrame]['name'] }}</h2>
                        <p class="text-muted" id="tryon-product-brand">{{ $frames[$selectedFrame]['brand'] }}</p>
                        <a class="btn btn-outline-primary rounded-pill" id="tryon-product-link" href="{{ route('shop.show', $frames[$selectedFrame]['slug']) }}">Product details</a>
                    </div>
                </div>
            </div>
            <script>
                window.BEALET_TRYON_FRAMES = @json($frames);
                window.BEALET_TRYON_SELECTED = @json($selectedFrame);
            </script>
            <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils@0.3.1675466862/camera_utils.js" crossorigin="anonymous" defer></script>
            <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh@0.4.1633559619/face_mesh.js" crossorigin="anonymous" defer></script>
            <script type="module" src="https://cdn.jsdelivr.net/npm/@google/model-viewer@4.0.0/dist/model-viewer.min.js"></script>
            <script src="{{ asset('js/try-on.js') }}?v={{ filemtime(public_path('js/try-on.js')) }}-{{ filesize(public_path('js/try-on.js')) }}" defer></script>
        @endif
    </section>
@endsection
