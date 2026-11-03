<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $pendingOrder = $this->sessionPendingOrder($request);
        if ($pendingOrder) {
            return view('checkout.pending', ['order' => $pendingOrder]);
        }

        $items = $this->cartItems($request);
        if ($items->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Your cart is empty.');
        }

        $subtotalPesewas = (int) $items->sum(
            static fn (object $item): int => Money::toPesewas($item->price) * (int) $item->quantity
        );
        $user = $request->user();
        $checkoutToken = (string) $request->session()->get('checkout_token');
        if ($checkoutToken === '') {
            $checkoutToken = (string) Str::uuid();
            $request->session()->put('checkout_token', $checkoutToken);
        }

        return view('checkout.index', compact('items', 'subtotalPesewas', 'user', 'checkoutToken'));
    }

    public function store(Request $request): RedirectResponse
    {
        $pendingOrder = $this->sessionPendingOrder($request);
        if ($pendingOrder) {
            return redirect()->route('checkout.pending', $pendingOrder);
        }

        $data = $request->validate([
            'checkout_token' => ['required', 'uuid'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^(?:\+?233|0)?[0-9\s()-]{9,14}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['required', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'prescription_enabled' => ['sometimes', 'boolean'],
            'frame_notes' => ['nullable', 'string', 'max:255'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'prescription.od_sphere' => ['nullable', 'numeric', 'between:-30,30'],
            'prescription.od_cylinder' => ['nullable', 'numeric', 'between:-20,20'],
            'prescription.od_axis' => ['nullable', 'integer', 'between:0,180'],
            'prescription.od_add' => ['nullable', 'numeric', 'between:0,20'],
            'prescription.os_sphere' => ['nullable', 'numeric', 'between:-30,30'],
            'prescription.os_cylinder' => ['nullable', 'numeric', 'between:-20,20'],
            'prescription.os_axis' => ['nullable', 'integer', 'between:0,180'],
            'prescription.os_add' => ['nullable', 'numeric', 'between:0,20'],
            'prescription.pd_far' => ['nullable', 'numeric', 'between:20,90'],
            'prescription.pd_near' => ['nullable', 'numeric', 'between:20,90'],
        ]);
        $checkoutToken = (string) $request->session()->get('checkout_token');
        if ($checkoutToken === '' || ! hash_equals($checkoutToken, (string) $data['checkout_token'])) {
            throw ValidationException::withMessages([
                'checkout_token' => 'This checkout form has expired. Please review your cart and try again.',
            ]);
        }

        $existingOrder = Order::where('checkout_token', $checkoutToken)->first();
        if ($existingOrder) {
            abort_unless($this->ownsOrder($request, $existingOrder), 404);

            return redirect()->route('checkout.pending', $existingOrder);
        }

        $phoneDigits = preg_replace('/\D+/', '', $data['phone']);
        $phone = str_starts_with($phoneDigits, '233')
            ? '+'.$phoneDigits
            : (str_starts_with($phoneDigits, '0') ? '+233'.substr($phoneDigits, 1) : '+233'.$phoneDigits);
        $cartItems = $this->cartItems($request);

        if ($cartItems->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Your cart is empty.');
        }

        $order = DB::transaction(function () use ($request, $data, $checkoutToken, $phone): Order {
            $cartQuery = $this->cartQuery($request);
            $cartRows = $cartQuery->lockForUpdate()->get();
            if ($cartRows->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $products = Product::query()
                ->whereIn('id', $cartRows->pluck('product_id'))
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotalPesewas = 0;
            foreach ($cartRows as $cartRow) {
                $product = $products->get($cartRow->product_id);
                if (! $product || (int) $product->stock < (int) $cartRow->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => 'A product in your cart is no longer available in the requested quantity.',
                    ]);
                }
                $subtotalPesewas += Money::toPesewas($product->price) * (int) $cartRow->quantity;
            }

            $subtotal = $subtotalPesewas / 100;
            $tax = round($subtotalPesewas * 20 / 120) / 100;
            $name = trim($data['name']);
            $trackingCode = $this->trackingCode($name);
            $shippingAddress = $name."\n".$data['address']."\n".$data['city'].', '.$data['region'];
            if (! empty($data['zip'])) {
                $shippingAddress .= ' '.$data['zip'];
            }
            $user = $request->user();

            $order = Order::create([
                'user_id' => $user?->id,
                'guest_name' => $name,
                'guest_email' => $data['email'],
                'order_phone' => $phone,
                'guest_phone' => $phone,
                'tracking_code' => $trackingCode,
                'checkout_token' => $checkoutToken,
                'subtotal' => $subtotal,
                'shipping_fee' => 0,
                'tax' => $tax,
                'discount' => 0,
                'total_amount' => $subtotal,
                'status' => 'pending',
                'payment_status' => 'pending',
                'shipping_address' => $shippingAddress,
                'shipping_city' => $data['city'],
                'shipping_state' => $data['region'],
                'shipping_zip' => $data['zip'] ?? null,
                'shipping_country' => 'Ghana',
                'stock_reserved' => true,
                'reservation_expires_at' => now()->addMinutes(30),
            ]);

            foreach ($cartRows as $cartRow) {
                $product = $products->get($cartRow->product_id);
                $product->decrement('stock', (int) $cartRow->quantity);
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => (int) $cartRow->quantity,
                ]);
            }

            if ($request->boolean('prescription_enabled')) {
                $prescription = array_filter(
                    (array) ($data['prescription'] ?? []),
                    static fn (mixed $value): bool => $value !== null && $value !== ''
                );
                $frameNotes = trim((string) ($data['frame_notes'] ?? ''));
                $customerNotes = trim((string) ($data['customer_notes'] ?? ''));
                if ($prescription !== [] || $frameNotes !== '' || $customerNotes !== '') {
                    $order->prescriptions()->create([
                        'prescription_source' => 'manual',
                        'frame_notes' => $frameNotes !== '' ? $frameNotes : null,
                        'manual_prescription' => $prescription !== [] ? json_encode($prescription, JSON_THROW_ON_ERROR) : null,
                        'customer_notes' => $customerNotes !== '' ? $customerNotes : null,
                    ]);
                }
            }

            $cartQuery->delete();

            return $order;
        });

        $request->session()->put('checkout_order_id', $order->id);
        $request->session()->forget('checkout_token');

        return redirect()->route('checkout.pending', $order);
    }

    public function showPendingPayment(Request $request, Order $order): View|RedirectResponse
    {
        abort_unless($this->ownsOrder($request, $order), 404);

        if ($order->payment_status === 'paid') {
            return redirect()->route('orders.confirmation', ['tracking_code' => $order->tracking_code]);
        }

        return view('checkout.pending', compact('order'));
    }

    private function sessionPendingOrder(Request $request): ?Order
    {
        $orderId = (int) $request->session()->get('checkout_order_id', 0);
        if ($orderId <= 0) {
            return null;
        }

        $order = Order::find($orderId);
        if (! $order || ! $this->ownsOrder($request, $order) || $order->payment_status !== 'pending') {
            $request->session()->forget('checkout_order_id');

            return null;
        }

        return $order;
    }

    private function ownsOrder(Request $request, Order $order): bool
    {
        if ($request->user()) {
            return (int) $order->user_id === (int) $request->user()->id;
        }

        return (int) $request->session()->get('checkout_order_id', 0) === (int) $order->id;
    }

    private function cartItems(Request $request)
    {
        return $this->cartQuery($request)
            ->join('products', 'products.id', '=', 'cart.product_id')
            ->where('products.is_active', true)
            ->select('cart.id as cart_id', 'cart.quantity', 'products.id as product_id', 'products.name', 'products.price', 'products.stock')
            ->orderBy('cart.id')
            ->get();
    }

    private function cartQuery(Request $request): Builder
    {
        return DB::table('cart')->where(function (Builder $query) use ($request): void {
            if ($request->user()) {
                $query->where('cart.user_id', $request->user()->id);
            } else {
                $query->whereNull('cart.user_id')
                    ->where('cart.session_id', $request->session()->getId());
            }
        });
    }

    private function trackingCode(string $name): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(3)
            ->map(static fn (string $part): string => Str::upper(substr($part, 0, 1)))
            ->implode('');

        return 'BOC/'.Str::upper(Str::random(6)).'/'.now()->format('Y').'/'.($initials ?: 'X');
    }
}
