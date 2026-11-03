<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta id="admin-theme-color" name="theme-color" content="#f5f7fb">
    <title>@yield('title', 'Admin | ' . config('app.name'))</title>
    <script>
        (() => {
            const theme = localStorage.getItem('bealet-admin-theme') || 'light';
            document.documentElement.dataset.adminTheme = theme === 'dark' ? 'dark' : 'light';
        })();
    </script>
    <link rel="icon" href="{{ asset('legacy-assets/images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/storefront.css') }}" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
</head>
<body class="admin-body">
    @php
        $isSuperAdmin = in_array(auth()->user()->admin_role, [null, '', 'super_admin'], true);
        $adminNavigation = [
            ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => 'fa-chart-pie', 'active' => 'admin.dashboard'],
            ['label' => 'Orders', 'route' => 'admin.orders.index', 'icon' => 'fa-bag-shopping', 'active' => 'admin.orders.*'],
            ['label' => 'Inventory', 'route' => 'admin.inventory.index', 'icon' => 'fa-boxes-stacked', 'active' => 'admin.inventory.*'],
            ['label' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'fa-users', 'active' => 'admin.customers.*'],
            ['label' => 'Appointments', 'route' => 'admin.content.appointments', 'icon' => 'fa-calendar-check', 'active' => 'admin.content.appointments*'],
            ['label' => 'Messages', 'route' => 'admin.content.contacts', 'icon' => 'fa-envelope', 'active' => 'admin.content.contacts*'],
            ['label' => 'Reviews', 'route' => 'admin.content.reviews', 'icon' => 'fa-star', 'active' => 'admin.content.reviews*'],
            ['label' => 'Blog', 'route' => 'admin.content.blog', 'icon' => 'fa-newspaper', 'active' => 'admin.content.blog*'],
        ];
        if ($isSuperAdmin) {
            $adminNavigation = array_merge($adminNavigation, [
                ['label' => 'Products', 'route' => 'admin.products.index', 'icon' => 'fa-glasses', 'active' => 'admin.products.*'],
                ['label' => 'Site settings', 'route' => 'admin.site.settings', 'icon' => 'fa-sliders', 'active' => 'admin.site.*'],
                ['label' => 'Staff', 'route' => 'admin.site.staff', 'icon' => 'fa-user-gear', 'active' => 'admin.site.staff*'],
                ['label' => 'Founder gallery', 'route' => 'admin.site.gallery', 'icon' => 'fa-images', 'active' => 'admin.site.gallery*'],
            ]);
        }
    @endphp
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                <img class="admin-brand-logo" src="{{ asset('legacy-assets/images/logo/logo.png') }}" alt="Bealet Optical Center">
                <span class="admin-brand-caption"><strong>BEALET</strong><small>OPTICAL ADMIN</small></span>
            </a>
            <div class="admin-sidebar-label">WORKSPACE</div>
            <nav class="admin-navigation" aria-label="Administration">
                @foreach ($adminNavigation as $item)
                    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['active']) ? 'active' : '' }}">
                        <i class="fa-solid {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="admin-sidebar-bottom">
                <a href="{{ route('home') }}"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>View storefront</span></a>
                <div class="admin-user-card">
                    <span class="admin-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="admin-user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ str($isSuperAdmin ? 'Super admin' : (auth()->user()->admin_role ?: 'Admin'))->replace('_', ' ')->title() }}</small></span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="admin-logout" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button>
                    </form>
                </div>
            </div>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-mobile-brand"><img src="{{ asset('legacy-assets/images/logo/logo.png') }}" alt=""> <span>ADMIN</span></div>
                <div class="admin-topbar-context">@yield('breadcrumb', 'Workspace')</div>
                <div class="admin-topbar-actions">
                    <button class="admin-theme-toggle" type="button" aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
                        <i class="fa-solid fa-moon" aria-hidden="true"></i><span class="admin-theme-toggle-label">Dark mode</span>
                    </button>
                    <a class="admin-topbar-store" href="{{ route('home') }}"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>View storefront</span></a>
                </div>
            </header>
            <main class="admin-content">
                @if (session('status'))
                    <div class="alert alert-success admin-alert" role="status">{{ session('status') }}</div>
                @endif
                @yield('admin-content')
            </main>
        </div>
    </div>
    <script>
        (() => {
            const toggle = document.querySelector('.admin-theme-toggle');
            const icon = toggle?.querySelector('i');
            const label = toggle?.querySelector('.admin-theme-toggle-label');
            const themeColor = document.querySelector('#admin-theme-color');
            const applyTheme = (theme) => {
                document.documentElement.dataset.adminTheme = theme;
                const dark = theme === 'dark';
                themeColor?.setAttribute('content', dark ? '#111827' : '#f5f7fb');
                toggle?.setAttribute('aria-pressed', String(dark));
                toggle?.setAttribute('aria-label', `Switch to ${dark ? 'light' : 'dark'} mode`);
                toggle?.setAttribute('title', `Switch to ${dark ? 'light' : 'dark'} mode`);
                if (icon) {
                    icon.className = `fa-solid ${dark ? 'fa-sun' : 'fa-moon'}`;
                }
                if (label) {
                    label.textContent = `${dark ? 'Light' : 'Dark'} mode`;
                }
            };

            applyTheme(document.documentElement.dataset.adminTheme || 'light');
            toggle?.addEventListener('click', () => {
                const nextTheme = document.documentElement.dataset.adminTheme === 'dark' ? 'light' : 'dark';
                localStorage.setItem('bealet-admin-theme', nextTheme);
                applyTheme(nextTheme);
            });
        })();
    </script>
</body>
</html>
