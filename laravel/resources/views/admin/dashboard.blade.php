@extends('admin.layout')

@section('title', 'Overview | Bealet Admin')
@section('breadcrumb', 'Overview')

@section('admin-content')
    <section class="admin-welcome">
        <div>
            <span class="admin-eyebrow"><i class="fa-regular fa-sun"></i> YOUR BUSINESS AT A GLANCE</span>
            <h1>Good {{ now()->format('H') < 12 ? 'morning' : (now()->format('H') < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}.</h1>
            <p>Here’s what’s happening at Bealet today.</p>
        </div>
        <div class="admin-today"><i class="fa-regular fa-calendar"></i> {{ now()->format('l, F j, Y') }}</div>
    </section>

    <section class="admin-metric-grid" aria-label="Business summary">
        <a class="admin-metric-card" href="{{ route('admin.orders.index') }}">
            <span class="admin-metric-icon metric-blue"><i class="fa-solid fa-bag-shopping"></i></span>
            <span class="admin-metric-label">Orders to process</span>
            <strong>{{ number_format($ordersToProcess) }}</strong>
            <span class="admin-metric-foot">Paid orders awaiting fulfillment <i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <article class="admin-metric-card">
            <span class="admin-metric-icon metric-green"><i class="fa-solid fa-chart-line"></i></span>
            <span class="admin-metric-label">Sales this month</span>
            <strong>GH₵ {{ number_format((float) $salesThisMonth, 2) }}</strong>
            <span class="admin-metric-foot">From successfully paid orders</span>
        </article>
        <a class="admin-metric-card" href="{{ route('admin.content.appointments') }}">
            <span class="admin-metric-icon metric-purple"><i class="fa-solid fa-calendar-day"></i></span>
            <span class="admin-metric-label">Appointments today</span>
            <strong>{{ number_format($appointmentsToday) }}</strong>
            <span class="admin-metric-foot">Pending or confirmed visits <i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <a class="admin-metric-card" href="{{ route('admin.inventory.index') }}">
            <span class="admin-metric-icon metric-amber"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span class="admin-metric-label">Low stock</span>
            <strong>{{ number_format($lowStockProducts) }}</strong>
            <span class="admin-metric-foot">Active products at or below threshold <i class="fa-solid fa-arrow-right"></i></span>
        </a>
    </section>

    <section class="admin-action-strip" aria-label="Action queues">
        <a href="{{ route('admin.content.contacts') }}" class="admin-action-item">
            <span class="admin-action-icon"><i class="fa-regular fa-envelope"></i></span>
            <span><strong>{{ number_format($unreadMessages) }} unread {{ \Illuminate\Support\Str::plural('message', $unreadMessages) }}</strong><small>Customer enquiries need a reply</small></span>
            <i class="fa-solid fa-chevron-right"></i>
        </a>
        <a href="{{ route('admin.content.reviews') }}" class="admin-action-item">
            <span class="admin-action-icon"><i class="fa-regular fa-star"></i></span>
            <span><strong>{{ number_format($pendingReviews) }} {{ \Illuminate\Support\Str::plural('review', $pendingReviews) }} to moderate</strong><small>Keep customer feedback up to date</small></span>
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </section>

    <section class="admin-quick-actions">
        <div>
            <h2>Quick actions</h2>
            <p>Jump straight into your daily tasks.</p>
        </div>
        <div class="admin-quick-links">
            @if (in_array(auth()->user()->admin_role, [null, '', 'super_admin'], true))
                <a href="{{ route('admin.products.index') }}"><i class="fa-solid fa-plus"></i> Manage products</a>
            @endif
            <a href="{{ route('admin.orders.index') }}"><i class="fa-solid fa-list-check"></i> Manage orders</a>
            <a href="{{ route('admin.content.appointments') }}"><i class="fa-solid fa-calendar-check"></i> View appointments</a>
            <a href="{{ route('admin.content.blog.create') }}"><i class="fa-solid fa-pen-to-square"></i> Write a blog post</a>
        </div>
    </section>

    <section class="admin-dashboard-columns">
        <article class="admin-panel">
            <div class="admin-panel-heading">
                <div><h2>Recent orders</h2><p>Your latest customer purchases</p></div>
                <a href="{{ route('admin.orders.index') }}">All orders <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            @if ($recentOrders->isEmpty())
                <div class="admin-empty-state"><span><i class="fa-solid fa-bag-shopping"></i></span><strong>No orders yet</strong><p>New customer orders will appear here.</p></div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>ORDER</th><th>CUSTOMER</th><th>DATE</th><th>TOTAL</th><th>STATUS</th></tr></thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td><a class="admin-order-code" href="{{ route('admin.orders.show', $order) }}">{{ $order->tracking_code }}</a></td>
                                    <td>{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}</td>
                                    <td>{{ $order->created_at?->format('M j') ?? '—' }}</td>
                                    <td class="text-nowrap">GH₵ {{ number_format((float) $order->total_amount, 2) }}</td>
                                    <td><span class="admin-status status-{{ $order->payment_status }}">{{ \Illuminate\Support\Str::headline($order->payment_status) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>

        <article class="admin-panel">
            <div class="admin-panel-heading">
                <div><h2>Upcoming appointments</h2><p>Next visits at your clinic</p></div>
                <a href="{{ route('admin.content.appointments') }}">View all <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            @if ($upcomingAppointments->isEmpty())
                <div class="admin-empty-state"><span><i class="fa-regular fa-calendar"></i></span><strong>Your schedule is clear</strong><p>Upcoming appointments will show here.</p></div>
            @else
                <div class="admin-appointment-list">
                    @foreach ($upcomingAppointments as $appointment)
                        <div class="admin-appointment-item">
                            <span class="admin-appointment-date"><strong>{{ $appointment->appointment_date->format('d') }}</strong><small>{{ $appointment->appointment_date->format('M') }}</small></span>
                            <span class="admin-appointment-copy"><strong>{{ $appointment->name }}</strong><small>{{ \Illuminate\Support\Str::headline($appointment->appointment_type) }} · {{ \Illuminate\Support\Carbon::parse($appointment->appointment_time)->format('g:i A') }}</small></span>
                            <span class="admin-status status-{{ $appointment->status }}">{{ \Illuminate\Support\Str::headline($appointment->status) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
@endsection
