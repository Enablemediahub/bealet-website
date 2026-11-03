@extends('storefront.layout')

@section('title', config('app.name') . ' | Premium Eyewear')

@php
    $introVideoPath = trim((string) ($settings['intro_video'] ?? ''));
    $introVideoUrl = $introVideoPath !== '' ? \App\Support\ContentAsset::url($introVideoPath) : null;
    $homepageReviewToastItems = $homepageReviews->map(fn (array $review): array => [
        'name' => $review['name'],
        'title' => $review['title'],
        'rating' => (int) $review['rating'],
        'comment' => \Illuminate\Support\Str::limit($review['comment'], 150),
        'image' => $review['image'] ? \App\Support\ContentAsset::url($review['image']) : null,
    ])->values()->all();
@endphp

@if ($introVideoUrl)
    @push('head')
        <script>
            if (sessionStorage.getItem('bealet-home-intro-played') !== '1') {
                document.documentElement.classList.add('home-intro-pending');
            }
        </script>
    @endpush

    @section('intro')
        <div class="site-intro-overlay" id="siteIntroOverlay" aria-hidden="true">
            <video class="site-intro-video" id="siteIntroVideo" data-video-src="{{ $introVideoUrl }}" muted playsinline preload="none" aria-label="Bealet Optical Center introduction"></video>
            <div class="site-intro-scrim"></div>
            <div class="site-intro-content">
                <div class="site-intro-brand">
                    <span class="site-intro-kicker"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> Opening Experience</span>
                    <button class="btn site-intro-skip" id="siteIntroSkipButton" type="button">Skip Intro</button>
                </div>
                <div class="site-intro-copy">
                    <h1>Focus is about to click into place.</h1>
                    <p>Your Bealet experience is loading with clarity, precision, and a sharper first impression.</p>
                </div>
                <div class="site-intro-actions">
                    <div class="site-intro-progress">
                        <small id="siteIntroStatus" role="status">Preparing the homepage...</small>
                        <div class="site-intro-progress-track" aria-hidden="true">
                            <div class="site-intro-progress-bar" id="siteIntroProgressBar"></div>
                        </div>
                    </div>
                    <button class="btn site-intro-play" id="siteIntroPlayButton" type="button" hidden>Play intro</button>
                </div>
            </div>
        </div>
    @endsection
@endif

@section('content')
    @php
        $heroImagePath = trim((string) ($settings['home_hero_image'] ?? ''));
        $heroImagePaths = array_values(array_unique(array_filter([
            $heroImagePath,
            'assets/uploads/hero/69ff8559146b5_1778353497.png',
            'assets/uploads/hero/69e7fbf7f1f06_1776810999.jpg',
            'assets/uploads/hero/69eb83f7670fb_1777042423.jpg',
        ])));
        $heroImages = array_map(fn (string $path): string => \App\Support\ContentAsset::url($path), $heroImagePaths);
        $heroMotionOptions = ['zoom', 'pan-left', 'pan-right'];
    @endphp
    <section class="storefront-hero">
        <div id="storefrontHeroCarousel" class="carousel slide carousel-fade storefront-hero-carousel" data-bs-ride="carousel" data-bs-interval="6500" data-bs-pause="hover" data-bs-wrap="true" aria-label="Featured eyewear">
            <div class="carousel-inner">
                @foreach ($heroImages as $index => $heroImage)
                    <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                        <div class="storefront-hero-slide storefront-hero-slide--{{ $heroMotionOptions[$index % count($heroMotionOptions)] }}" style="--storefront-hero-image: url('{{ $heroImage }}')"></div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="storefront-hero-overlay" aria-hidden="true"></div>
        <div class="container-lg">
            <div class="storefront-hero-copy">
                <span class="storefront-eyebrow">Precision Eyewear Experience</span>
                <h1>{{ $settings['homepage_hero_title'] ?? 'See the world in perfect focus.' }}</h1>
                <p>{{ $settings['homepage_hero_subtitle'] ?? 'Premium eyewear with a modern fitting experience. Explore bold frames, use AR try-on, and book specialists in seconds.' }}</p>
                <div class="d-flex flex-wrap gap-3">
                    <a class="btn btn-light btn-lg" href="{{ route('shop.index') }}"><i class="fa-solid fa-bag-shopping me-2"></i>Shop Frames</a>
                    <a class="btn btn-outline-light btn-lg" href="{{ route('tryon.index') }}"><i class="fa-solid fa-vr-cardboard me-2"></i>Start AR Try-On</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section-spacing">
        <div class="container-lg">
            <div class="section-title">
                <h2>Why Choose Bealet?</h2>
                <p>Experience eyewear shopping reimagined</p>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['icon' => 'fa-vr-cardboard', 'title' => 'AR Try-On', 'description' => 'See how frames look on your face before buying using our advanced AR technology.'],
                    ['icon' => 'fa-truck', 'title' => 'Fast Shipping', 'description' => 'Get your orders delivered quickly with real-time tracking updates.'],
                    ['icon' => 'fa-calendar', 'title' => 'Easy Booking', 'description' => 'Schedule appointments with our optometry specialists easily.'],
                    ['icon' => 'fa-headset', 'title' => 'Support 24/7', 'description' => 'Our customer service team is always ready to help you.'],
                ] as $feature)
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center home-feature-card h-100">
                            <div class="card-body">
                                <span class="home-feature-icon"><i class="fa-solid {{ $feature['icon'] }}"></i></span>
                                <h3 class="h5 card-title">{{ $feature['title'] }}</h3>
                                <p class="card-text">{{ $feature['description'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-spacing pt-0 bg-light">
        <div class="container-lg">
            <div class="section-title">
                <h2>Featured Products</h2>
                <p>Discover our latest collection of premium eyewear</p>
            </div>
            @if ($featuredProducts->isNotEmpty())
                <div class="row g-4">
                    @foreach ($featuredProducts as $product)
                        @include('storefront.shop._product-card', ['product' => $product, 'slideshow' => true])
                    @endforeach
                </div>
            @else
                <div class="storefront-empty">
                    <p class="mb-3">Our featured eyewear collection is being prepared.</p>
                    <a class="btn btn-primary" href="{{ route('shop.index') }}">Browse the shop</a>
                </div>
            @endif
        </div>
    </section>

    <section class="container-lg storefront-section">
        <div class="storefront-reviews-showcase">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <span class="storefront-eyebrow storefront-eyebrow-dark">Experiences from our community</span>
                    <h2>What Customers Are Saying</h2>
                    <p class="text-muted mb-0">Browse testimonials and product feedback from customers who have shopped with Bealet.</p>
                </div>
                <a class="btn btn-outline-primary rounded-pill" href="{{ route('reviews.index') }}"><i class="fa-solid fa-star me-2"></i>Open Testimonials</a>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="reviews-summary-mini h-100">
                        <strong>{{ number_format($combinedAverageRating, 1) }}/5</strong>
                        <span>Average approved rating across testimonials and product reviews</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="reviews-summary-mini h-100">
                        <strong>{{ $totalPublishedReviews }}</strong>
                        <span>Published testimonials and product reviews</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="reviews-summary-mini h-100">
                        <strong>{{ $customerReviewCount }} + {{ $productReviewCount }}</strong>
                        <span>General testimonials and product reviews in the community</span>
                    </div>
                </div>
            </div>

            @if ($homepageReviews->isNotEmpty())
                <div class="row g-3">
                    @foreach ($homepageReviews as $review)
                        <div class="col-lg-4" data-review-reveal style="--review-reveal-delay: {{ $loop->index * 100 }}ms">
                            <article class="home-review-card h-100">
                                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        @if ($review['image'])
                                            <img class="review-avatar" src="{{ \App\Support\ContentAsset::url($review['image']) }}" alt="{{ $review['name'] }}" loading="lazy">
                                        @else
                                            <span class="review-avatar home-review-initials" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($review['name'], 0, 1)) }}</span>
                                        @endif
                                        <div>
                                            <h3 class="h6 mb-1">{{ $review['name'] }}</h3>
                                            <div class="small text-muted mb-1">{{ $review['title'] }}</div>
                                            <div class="review-stars" aria-label="{{ $review['rating'] }} out of 5 stars">
                                                @for ($star = 1; $star <= 5; $star++)
                                                    <i class="fa-{{ $star <= $review['rating'] ? 'solid' : 'regular' }} fa-star"></i>
                                                @endfor
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted text-nowrap">{{ $review['date']?->format('M j, Y') }}</small>
                                </div>
                                @if ($review['type'] === 'product' && $review['testimonial_image'])
                                    <img class="product-testimonial-thumb mb-3" src="{{ \App\Support\ContentAsset::url($review['testimonial_image']) }}" alt="{{ $review['product_name'] }} testimonial photo" loading="lazy">
                                @endif
                                <p class="review-comment mb-0">{{ $review['comment'] }}</p>
                            </article>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="storefront-empty">
                    <h3 class="h5">The customer review wall is ready.</h3>
                    <p>Once the first reviews are approved, they will appear here.</p>
                    <a href="{{ route('reviews.index') }}">Visit Testimonials</a>
                </div>
            @endif
        </div>
    </section>

    @if ($homepageReviews->isNotEmpty())
        <aside class="homepage-review-toast" id="homepageReviewToast" aria-live="polite" aria-atomic="true" aria-hidden="true" inert>
            <div class="homepage-review-toast__accent"></div>
            <div class="homepage-review-toast__body">
                <div class="homepage-review-toast__heading">
                    <span class="homepage-review-toast__avatar" id="homepageReviewAvatar" aria-hidden="true"></span>
                    <div class="homepage-review-toast__identity">
                        <strong id="homepageReviewName"></strong>
                        <span id="homepageReviewTitle"></span>
                    </div>
                    <button class="homepage-review-toast__close" type="button" aria-label="Dismiss customer review"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="homepage-review-toast__stars" id="homepageReviewStars" aria-label="Customer rating"></div>
                <p id="homepageReviewComment"></p>
                <a href="{{ route('reviews.index') }}">Read customer stories <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        </aside>
    @endif

    <section class="container-lg storefront-section pt-0">
        <div class="founder-spotlight">
            <div class="row align-items-center g-4">
                <div class="col-lg-4">
                    @if (!empty($settings['founder_thumbnail']))
                        <img class="founder-spotlight-image" src="{{ \App\Support\ContentAsset::url($settings['founder_thumbnail']) }}" alt="{{ $settings['founder_name'] ?? 'Bealet founder' }}" loading="lazy">
                    @else
                        <div class="founder-spotlight-placeholder" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></div>
                    @endif
                </div>
                <div class="col-lg-8">
                    <span class="storefront-eyebrow storefront-eyebrow-dark"><i class="fa-solid fa-landmark me-2"></i>Founder Spotlight</span>
                    <h2 class="mb-2">{{ $settings['founder_name'] ?? 'Madam Founder' }}</h2>
                    <p class="text-primary fw-semibold mb-3">{{ $settings['founder_role'] ?? 'Founder' }}</p>
                    <p class="lead text-muted mb-3">{{ $settings['founder_short_bio'] ?? 'A short introduction to the founder will appear here once it has been added from the admin panel.' }}</p>
                    @if (!empty($settings['founder_quote']))
                        <blockquote class="founder-quote mb-4">“{{ $settings['founder_quote'] }}”</blockquote>
                    @endif
                    <a class="btn btn-primary btn-lg rounded-pill" href="{{ route('founder') }}"><i class="fa-solid fa-book-open me-2"></i>Read Her Story</a>
                </div>
            </div>
        </div>
    </section>

    <section class="container-lg storefront-section">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <div class="services-panel">
                    <span class="storefront-eyebrow storefront-eyebrow-dark">Care for your vision</span>
                    <h2 class="mb-4">Our Services</h2>
                    <div class="home-service-item">
                        <h3 class="h5"><i class="fa-solid fa-circle-check text-success me-2"></i>Professional Eye Testing</h3>
                        <p>Our certified optometrists provide comprehensive eye exams to determine your exact prescription.</p>
                    </div>
                    <div class="home-service-item">
                        <h3 class="h5"><i class="fa-solid fa-circle-check text-success me-2"></i>Frame Fitting &amp; Styling</h3>
                        <p>Get expert advice on choosing frames that suit your face shape and personal style.</p>
                    </div>
                    <div class="home-service-item">
                        <h3 class="h5"><i class="fa-solid fa-circle-check text-success me-2"></i>Comprehensive Eye Examination</h3>
                        <p>Choose from lens options including progressive, photochromic, and blue-light-blocking lenses.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-primary rounded-pill" href="{{ route('appointments.create') }}"><i class="fa-solid fa-calendar me-2"></i>Book an Appointment</a>
                        <a class="btn btn-outline-primary rounded-pill" href="{{ route('team') }}"><i class="fa-solid fa-people-group me-2"></i>Meet Our Staff</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="services-visual"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i></div>
            </div>
        </div>
    </section>

    @if ($blogs->isNotEmpty())
        <section class="storefront-section bg-light">
            <div class="container-lg">
                <div class="storefront-section-heading">
                    <div>
                        <span class="storefront-eyebrow storefront-eyebrow-dark">Tips, trends, and insights</span>
                        <h2>Latest from Our Blog</h2>
                    </div>
                    <a class="storefront-text-link" href="{{ route('blog.index') }}">View all articles <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="row g-4">
                    @foreach ($blogs as $post)
                        <div class="col-md-6 col-lg-4">
                            <article class="card home-blog-card h-100">
                                @if ($post->featured_image)
                                    <img class="card-img-top" src="{{ \App\Support\ContentAsset::blogUrl($post->featured_image) }}" alt="{{ $post->title }}" loading="lazy">
                                @endif
                                <div class="card-body">
                                    <h3 class="h5 card-title">{{ $post->title }}</h3>
                                    <p class="card-text text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: $post->content), 110) }}</p>
                                    <small class="text-muted"><i class="fa-regular fa-calendar me-2"></i>{{ $post->published_at?->format('M j, Y') ?? $post->created_at?->format('M j, Y') }}</small>
                                </div>
                                <div class="card-footer border-0 bg-transparent pt-0">
                                    <a class="btn btn-sm btn-primary rounded-pill w-100" href="{{ route('blog.show', $post->slug) }}"><i class="fa-solid fa-arrow-right me-2"></i>Read More</a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="container-lg storefront-section">
        <div class="home-cta-panel text-center">
            <h2 class="mb-3">Ready to Experience Better Vision?</h2>
            <p class="lead mb-4">Find your perfect eyewear with Bealet Optical Center.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a class="btn btn-light btn-lg rounded-pill" href="{{ route('shop.index') }}"><i class="fa-solid fa-bag-shopping me-2"></i>Shop Now</a>
                <a class="btn btn-outline-light btn-lg rounded-pill" href="{{ route('tryon.index') }}"><i class="fa-solid fa-vr-cardboard me-2"></i>Try AR Try-On</a>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('img[data-fallback-hide]').forEach((image) => {
            const hideIfMissing = () => {
                if (!image.naturalWidth) {
                    image.hidden = true;
                }
            };
            image.addEventListener('error', hideIfMissing, { once: true });
            if (image.complete) {
                hideIfMissing();
            }
        });
    </script>

    @if ($introVideoUrl)
        <script>
            (() => {
                const overlay = document.querySelector('#siteIntroOverlay');
                const video = document.querySelector('#siteIntroVideo');
                const skipButton = document.querySelector('#siteIntroSkipButton');
                const playButton = document.querySelector('#siteIntroPlayButton');
                const progressBar = document.querySelector('#siteIntroProgressBar');
                const status = document.querySelector('#siteIntroStatus');
                if (!overlay || !video || !document.documentElement.classList.contains('home-intro-pending')) {
                    return;
                }

                const finishIntro = () => {
                    if (overlay.dataset.finished === 'true') {
                        return;
                    }
                    overlay.dataset.finished = 'true';
                    sessionStorage.setItem('bealet-home-intro-played', '1');
                    overlay.classList.add('is-hidden');
                    overlay.setAttribute('aria-hidden', 'true');
                    document.documentElement.classList.remove('home-intro-pending');
                    document.dispatchEvent(new CustomEvent('bealet:intro-complete'));
                    window.setTimeout(() => {
                        video.pause();
                        video.removeAttribute('src');
                        video.load();
                    }, 800);
                };

                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    finishIntro();
                    return;
                }

                overlay.setAttribute('aria-hidden', 'false');
                video.src = video.dataset.videoSrc;
                video.load();

                const syncProgress = () => {
                    if (!video.duration || Number.isNaN(video.duration)) {
                        return;
                    }
                    const progress = Math.min(video.currentTime / video.duration, 1);
                    progressBar.style.width = `${(progress * 100).toFixed(1)}%`;
                    status.textContent = progress >= 0.96 ? 'Focus locked. Opening homepage...' : 'Loading in sharp focus...';
                };

                skipButton.addEventListener('click', finishIntro);
                playButton.addEventListener('click', () => {
                    video.play().then(() => {
                        playButton.hidden = true;
                        status.textContent = 'Opening sequence is now playing...';
                    }).catch(() => {
                        status.textContent = 'The introduction could not be played. Skip to continue.';
                    });
                });
                video.addEventListener('ended', finishIntro, { once: true });
                video.addEventListener('timeupdate', syncProgress);
                video.addEventListener('canplay', () => {
                    status.textContent = 'Opening sequence is now playing...';
                }, { once: true });
                video.addEventListener('error', finishIntro, { once: true });
                video.play().then(() => {
                    status.textContent = 'Opening sequence is now playing...';
                }).catch(() => {
                    status.textContent = 'Tap play to watch the opening sequence.';
                    playButton.hidden = false;
                });
            })();
        </script>
    @endif

    <script>
        (() => {
            const reviewCards = document.querySelectorAll('[data-review-reveal]');
            if (!reviewCards.length) {
                return;
            }

            if (!('IntersectionObserver' in window)) {
                reviewCards.forEach((card) => card.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries, currentObserver) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        currentObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            reviewCards.forEach((card) => observer.observe(card));
        })();

        (() => {
            const toast = document.querySelector('#homepageReviewToast');
            if (!toast) {
                return;
            }

            const reviews = @json($homepageReviewToastItems);
            const avatar = toast.querySelector('#homepageReviewAvatar');
            const name = toast.querySelector('#homepageReviewName');
            const title = toast.querySelector('#homepageReviewTitle');
            const stars = toast.querySelector('#homepageReviewStars');
            const comment = toast.querySelector('#homepageReviewComment');
            const closeButton = toast.querySelector('.homepage-review-toast__close');
            let currentReview = 0;
            let showTimer;
            let hideTimer;
            let paused = false;

            const hideToast = () => {
                toast.classList.remove('is-visible');
                toast.setAttribute('aria-hidden', 'true');
                toast.setAttribute('inert', '');
                scheduleNext(14000);
            };

            const scheduleNext = (delay) => {
                window.clearTimeout(showTimer);
                showTimer = window.setTimeout(showToast, delay);
            };

            const showToast = () => {
                if (document.documentElement.classList.contains('home-intro-pending')) {
                    scheduleNext(1000);
                    return;
                }
                const review = reviews[currentReview];
                currentReview = (currentReview + 1) % reviews.length;
                name.textContent = review.name;
                title.textContent = review.title;
                comment.textContent = review.comment;
                stars.textContent = '★'.repeat(Math.max(0, Math.min(5, review.rating))) + '☆'.repeat(5 - Math.max(0, Math.min(5, review.rating)));
                stars.setAttribute('aria-label', `${review.rating} out of 5 stars`);
                avatar.textContent = review.image ? '' : review.name.trim().charAt(0).toUpperCase();
                avatar.style.backgroundImage = review.image ? `url("${review.image.replaceAll('"', '%22')}")` : '';
                toast.removeAttribute('inert');
                toast.classList.add('is-visible');
                toast.setAttribute('aria-hidden', 'false');
                if (!paused) {
                    hideTimer = window.setTimeout(hideToast, 7000);
                }
            };

            toast.addEventListener('mouseenter', () => {
                paused = true;
                window.clearTimeout(hideTimer);
            });
            toast.addEventListener('mouseleave', () => {
                paused = false;
                if (toast.classList.contains('is-visible')) {
                    hideTimer = window.setTimeout(hideToast, 7000);
                }
            });
            toast.addEventListener('focusin', () => {
                paused = true;
                window.clearTimeout(hideTimer);
            });
            toast.addEventListener('focusout', (event) => {
                if (!toast.contains(event.relatedTarget)) {
                    paused = false;
                    if (toast.classList.contains('is-visible')) {
                        hideTimer = window.setTimeout(hideToast, 7000);
                    }
                }
            });
            closeButton.addEventListener('click', hideToast);

            document.addEventListener('bealet:intro-complete', () => scheduleNext(5000), { once: true });
            if (!document.documentElement.classList.contains('home-intro-pending')) {
                scheduleNext(5000);
            }
        })();
    </script>
@endpush
