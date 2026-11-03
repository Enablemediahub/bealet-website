<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'Discover eyewear and optical care from Bealet Optical Center.')">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', config('app.name'))</title>
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:title" content="@yield('og_title', config('app.name'))">
    <meta property="og:description" content="@yield('meta_description', 'Discover eyewear and optical care from Bealet Optical Center.')">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
        <meta property="og:image:alt" content="@yield('og_title', config('app.name'))">
    @endif
    <link rel="icon" href="{{ asset('legacy-assets/images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('legacy-assets/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('css/storefront.css') }}?v={{ filemtime(public_path('css/storefront.css')) }}" rel="stylesheet">
    @stack('head')
</head>
<body>
    @yield('intro')
    @php
        $footerCompanyName = $siteSettings['company_name'] ?? config('app.name');
        $whatsAppUrl = \App\Support\WhatsAppLink::contact(
            $siteSettings['whatsapp_phone'] ?? null,
            $footerCompanyName,
        );
    @endphp
    <header class="site-header-wrap {{ request()->routeIs('home') ? 'site-header-wrap--hero' : '' }}">
        <nav class="navbar navbar-expand-lg site-navbar" aria-label="Main navigation">
            <div class="container-lg">
                <a class="navbar-brand site-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                    <span class="brand-mark">
                        <img class="site-logo-image" src="{{ !empty($siteSettings['logo_path']) ? \App\Support\ContentAsset::url($siteSettings['logo_path']) : asset('legacy-assets/images/logo/logo.png') }}" alt="{{ $siteSettings['company_name'] ?? config('app.name') }}">
                    </span>
                    <span class="brand-copy">
                        <span class="brand-text">{{ $siteSettings['company_name'] ?? config('app.name') }}</span>
                        <span class="brand-subtext">{{ $siteSettings['tagline'] ?? 'Premium Eyewear and Optical Care' }}</span>
                    </span>
                </a>
                <button class="navbar-toggler site-navbar-toggle ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#storefrontNavigation" aria-controls="storefrontNavigation" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="storefrontNavigation">
                    <div class="site-nav-panel ms-auto d-flex flex-column flex-lg-row align-items-lg-center">
                        <ul class="navbar-nav site-nav-links flex-column flex-lg-row mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link px-3 py-2 {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link px-3 py-2 {{ request()->routeIs('shop.*') ? 'active' : '' }}" href="{{ route('shop.index') }}" @if(request()->routeIs('shop.*')) aria-current="page" @endif>Shop</a>
                            </li>
                            <li class="nav-item"><a class="nav-link px-3 py-2 {{ request()->routeIs('tryon.*') ? 'active' : '' }}" href="{{ route('tryon.index') }}" @if(request()->routeIs('tryon.*')) aria-current="page" @endif>Try-On</a></li>
                            <li class="nav-item"><a class="nav-link px-3 py-2 {{ request()->routeIs('appointments.*') ? 'active' : '' }}" href="{{ route('appointments.create') }}" @if(request()->routeIs('appointments.*')) aria-current="page" @endif>Appointments</a></li>
                            <li class="nav-item"><a class="nav-link px-3 py-2 {{ request()->routeIs('blog.*') ? 'active' : '' }}" href="{{ route('blog.index') }}" @if(request()->routeIs('blog.*')) aria-current="page" @endif>Blog</a></li>
                            <li class="nav-item"><a class="nav-link px-3 py-2 {{ request()->routeIs('reviews.*') ? 'active' : '' }}" href="{{ route('reviews.index') }}" @if(request()->routeIs('reviews.*')) aria-current="page" @endif>Reviews</a></li>
                            <li class="nav-item"><a class="nav-link px-3 py-2 {{ request()->routeIs('contact.*') ? 'active' : '' }}" href="{{ route('contact.index') }}" @if(request()->routeIs('contact.*')) aria-current="page" @endif>Contact</a></li>
                        </ul>
                        <div class="storefront-nav-actions">
                            @if ($whatsAppUrl)
                                <a class="storefront-nav-whatsapp" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>WhatsApp</span>
                                </a>
                            @endif
                            @auth
                                <div class="dropdown">
                                    <button class="btn btn-outline-primary rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-regular fa-user me-1"></i>Account
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('account.index') }}">My profile</a></li>
                                        <li><a class="dropdown-item" href="{{ route('account.orders') }}">My orders</a></li>
                                        <li><a class="dropdown-item" href="{{ route('appointments.index') }}">My appointments</a></li>
                                        @if (auth()->user()->is_admin)
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin dashboard</a></li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button class="dropdown-item text-danger" type="submit">Sign out</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            @else
                                <a class="btn btn-outline-primary rounded-pill" href="{{ route('login') }}">Sign in</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="storefront-footer">
        <div class="container-lg">
            <div class="row gy-4 mb-4">
                <div class="col-md-3 footer-section">
                    <h5>About {{ $footerCompanyName }}</h5>
                    <p class="footer-link">Premium eyewear and optical services with cutting-edge AR technology for the perfect fit.</p>
                    <div class="social-links">
                        @foreach ([
                            ['key' => 'facebook_url', 'label' => 'Facebook', 'icon' => 'fa-facebook-f'],
                            ['key' => 'twitter_url', 'label' => 'X', 'icon' => 'fa-x-twitter'],
                            ['key' => 'instagram_url', 'label' => 'Instagram', 'icon' => 'fa-instagram'],
                            ['key' => 'linkedin_url', 'label' => 'LinkedIn', 'icon' => 'fa-linkedin-in'],
                            ['key' => 'tiktok_url', 'label' => 'TikTok', 'icon' => 'fa-tiktok'],
                        ] as $social)
                            @if (!empty($siteSettings[$social['key']]))
                                <a class="social-icon" href="{{ $siteSettings[$social['key']] }}" title="{{ $social['label'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-brands {{ $social['icon'] }}"></i>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="col-md-3 footer-section">
                    <h5>Quick Links</h5>
                    <a class="footer-link d-block" href="{{ route('home') }}"><i class="fa-solid fa-chevron-right"></i> Home</a>
                    <a class="footer-link d-block" href="{{ route('shop.index') }}"><i class="fa-solid fa-chevron-right"></i> Shop</a>
                    <a class="footer-link d-block" href="{{ route('tryon.index') }}"><i class="fa-solid fa-chevron-right"></i> AR Try-On</a>
                    <a class="footer-link d-block" href="{{ route('appointments.create') }}"><i class="fa-solid fa-chevron-right"></i> Book Appointment</a>
                    <a class="footer-link d-block" href="{{ route('reviews.index') }}"><i class="fa-solid fa-chevron-right"></i> Testimonials</a>
                </div>
                <div class="col-md-3 footer-section">
                    <h5>Customer Service</h5>
                    <a class="footer-link d-block" href="{{ route('contact.index') }}"><i class="fa-solid fa-chevron-right"></i> Contact Us</a>
                    <a class="footer-link d-block" href="{{ route('account.orders') }}"><i class="fa-solid fa-chevron-right"></i> My Orders</a>
                    <a class="footer-link d-block" href="{{ route('appointments.index') }}"><i class="fa-solid fa-chevron-right"></i> My Appointments</a>
                    <a class="footer-link d-block" href="{{ route('blog.index') }}"><i class="fa-solid fa-chevron-right"></i> Blog</a>
                </div>
                <div class="col-md-3 footer-section">
                    <h5>Newsletter</h5>
                    <p class="footer-link">Subscribe to get special offers and updates!</p>
                    <form method="POST" action="{{ route('newsletter.store') }}" class="mt-3">
                        @csrf
                        <div class="input-group">
                            <label class="visually-hidden" for="newsletter-email">Email address</label>
                            <input class="form-control" id="newsletter-email" name="email" type="email" placeholder="Your email" required>
                            <button class="btn btn-primary" type="submit" aria-label="Subscribe to newsletter">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                    @if (session('newsletter_status'))
                        <p class="small mt-2 mb-0" role="status">{{ session('newsletter_status') }}</p>
                    @endif
                    <div class="mt-3">
                        <p class="footer-link mb-1"><strong>Hours</strong></p>
                        <p class="footer-link mb-1">Monday - Friday: 8:00 AM - 5:00 PM</p>
                        <p class="footer-link mb-1">Saturday: 8:00 AM - 2:00 PM</p>
                        <p class="footer-link mb-1">Sunday: Closed</p>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; {{ now()->year }} {{ $footerCompanyName }}. All rights reserved.</p>
                <p class="small mt-2 mb-0">Designed and developed by Dale Quist (Enable Technologies)</p>
            </div>
        </div>
    </footer>

    @if ($whatsAppUrl)
        <a class="floating-whatsapp-button" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp" title="WhatsApp us">
            <span class="floating-whatsapp-button__icon"><i class="fa-brands fa-whatsapp"></i></span>
            <span class="floating-whatsapp-button__text">WhatsApp Us</span>
        </a>
    @endif

    <script>
        window.BASE_URL = @json(url('/'));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
