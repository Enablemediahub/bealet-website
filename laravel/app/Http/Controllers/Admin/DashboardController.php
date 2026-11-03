<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\CustomerReview;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();
        $pendingReviews = CustomerReview::query()->where('is_approved', false)->count()
            + Review::query()->where('is_approved', false)->count();

        return view('admin.dashboard', [
            'ordersToProcess' => Order::query()
                ->where('payment_status', 'paid')
                ->whereIn('status', ['pending', 'processing'])
                ->count(),
            'salesThisMonth' => Order::query()
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total_amount'),
            'appointmentsToday' => Appointment::query()
                ->whereDate('appointment_date', $today)
                ->whereIn('status', ['pending', 'confirmed'])
                ->count(),
            'lowStockProducts' => Product::query()
                ->where('is_active', true)
                ->whereColumn('stock', '<=', 'low_stock_threshold')
                ->count(),
            'unreadMessages' => Contact::query()->where('is_read', false)->count(),
            'pendingReviews' => $pendingReviews,
            'recentOrders' => Order::query()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(6)
                ->get(),
            'upcomingAppointments' => Appointment::query()
                ->whereDate('appointment_date', '>=', $today)
                ->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->limit(5)
                ->get(),
        ]);
    }
}
