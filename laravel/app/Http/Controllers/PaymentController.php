<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\PaystackService;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function start(Request $request, Order $order, PaystackService $paystack): Response|RedirectResponse
    {
        abort_unless($this->ownsOrder($request, $order), 404);
        if ($order->payment_status === 'paid') {
            return redirect()->route('orders.confirmation', ['tracking_code' => $order->tracking_code]);
        }
        if (! $order->stock_reserved || $order->reservation_expires_at?->isPast()) {
            return back()->withErrors(['payment' => 'This checkout has expired. Please review your cart and try again.']);
        }

        try {
            $checkout = $paystack->initialize($order);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Paystack initialization failed.', [
                'order_id' => $order->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'payment' => $exception instanceof RuntimeException && $exception->getMessage() === 'Paystack is not configured.'
                    ? 'Online payment is not configured yet. Please try again later.'
                    : 'We could not start the payment. Your order is saved; please retry in a moment.',
            ]);
        }

        $order->forceFill([
            'paystack_reference' => $checkout['reference'],
            'paystack_access_code' => $checkout['access_code'],
        ])->save();

        return redirect()->away($checkout['authorization_url']);
    }

    public function callback(Request $request, PaystackService $paystack): Response|RedirectResponse
    {
        $reference = trim((string) $request->query('reference', ''));
        if ($reference === '' || strlen($reference) > 100) {
            return redirect()->route('shop.index')->withErrors(['payment' => 'The payment reference is missing or invalid.']);
        }

        $order = Order::where('paystack_reference', $reference)->first();
        if (! $order) {
            Log::warning('Paystack callback referenced an unknown transaction.', ['reference' => $reference]);

            return redirect()->route('shop.index')->withErrors(['payment' => 'We could not find this order. Contact support if you were charged.']);
        }

        try {
            $payment = $paystack->verify($reference);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Paystack verification failed.', [
                'order_id' => $order->id,
                'reference' => $reference,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('checkout.pending', $order)->withErrors([
                'payment' => 'We could not confirm the payment yet. Your order is saved and can be checked again.',
            ]);
        }

        if (
            (string) data_get($payment, 'reference') !== $reference
            || (int) data_get($payment, 'metadata.order_id', 0) !== (int) $order->id
            || (string) data_get($payment, 'metadata.tracking_code', '') !== (string) $order->tracking_code
        ) {
            Log::critical('Paystack verification metadata did not match the order.', [
                'order_id' => $order->id,
                'reference' => $reference,
            ]);

            return response('Payment verification details did not match this order.', 422);
        }

        if ((string) data_get($payment, 'status') !== 'success') {
            if (in_array((string) data_get($payment, 'status'), ['failed', 'abandoned', 'reversed'], true)) {
                $this->failAndRelease($order);
                $request->session()->forget('checkout_order_id');
                $request->session()->put('checkout_token', (string) Str::uuid());

                return redirect()->route('checkout.index')->withErrors([
                    'payment' => 'Payment did not complete. Your cart is ready for another attempt.',
                ]);
            }

            return redirect()->route('checkout.pending', $order)->withErrors([
                'payment' => 'Payment is still processing. Please check again shortly.',
            ]);
        }

        if (
            (int) data_get($payment, 'amount', -1) !== Money::toPesewas($order->total_amount)
            || strtoupper((string) data_get($payment, 'currency', '')) !== strtoupper((string) config('services.paystack.currency'))
        ) {
            Log::critical('Paystack amount or currency did not match the order.', [
                'order_id' => $order->id,
                'reference' => $reference,
            ]);

            return response('Payment amount or currency did not match this order.', 422);
        }

        $stockAvailable = DB::transaction(function () use ($order): bool {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->payment_status === 'paid') {
                return true;
            }

            if (! $lockedOrder->stock_reserved) {
                $items = $lockedOrder->items()->orderBy('product_id')->get();
                $products = Product::query()
                    ->whereIn('id', $items->pluck('product_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    if (! $product || (int) $product->stock < (int) $item->quantity) {
                        $lockedOrder->forceFill([
                            'payment_status' => 'paid',
                            'status' => 'pending',
                        ])->save();

                        return false;
                    }
                }

                foreach ($items as $item) {
                    $products->get($item->product_id)->decrement('stock', (int) $item->quantity);
                }
            }

            $lockedOrder->forceFill([
                'payment_status' => 'paid',
                'status' => 'processing',
                'stock_reserved' => false,
                'reservation_expires_at' => null,
            ])->save();

            return true;
        });

        if (! $stockAvailable) {
            Log::critical('Payment succeeded after reserved stock was no longer available; fulfillment needs review.', [
                'order_id' => $order->id,
                'reference' => $reference,
            ]);
        }

        $this->clearCart($request, $order);
        $request->session()->put('confirmed_order_id', $order->id);
        $request->session()->forget(['checkout_order_id', 'checkout_token']);

        return redirect()->route('orders.confirmation', ['tracking_code' => $order->tracking_code]);
    }

    public function confirmation(Request $request): View
    {
        $trackingCode = trim((string) $request->query('tracking_code', ''));
        abort_if($trackingCode === '', 404);
        $order = Order::with('items')
            ->where('tracking_code', $trackingCode)
            ->firstOrFail();
        abort_unless(
            ($request->user() && (int) $order->user_id === (int) $request->user()->id)
                || (int) $request->session()->get('confirmed_order_id', 0) === (int) $order->id
                || (int) $request->session()->get('checkout_order_id', 0) === (int) $order->id,
            404
        );

        return view('checkout.confirmation', compact('order'));
    }

    private function failAndRelease(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->payment_status === 'paid' || ! $lockedOrder->stock_reserved) {
                return;
            }

            $items = $lockedOrder->items()->orderBy('product_id')->get();
            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                if ($product = $products->get($item->product_id)) {
                    $product->increment('stock', (int) $item->quantity);
                }
            }

            $lockedOrder->forceFill([
                'payment_status' => 'failed',
                'stock_reserved' => false,
                'reservation_expires_at' => null,
            ])->save();
        });
    }

    private function clearCart(Request $request, Order $order): void
    {
        $query = DB::table('cart');
        if ($order->user_id) {
            $query->where('user_id', $order->user_id);
        } else {
            $query->whereNull('user_id')->where('session_id', $request->session()->getId());
        }
        $query->delete();
    }

    private function ownsOrder(Request $request, Order $order): bool
    {
        if ($request->user()) {
            return (int) $order->user_id === (int) $request->user()->id;
        }

        return (int) $request->session()->get('checkout_order_id', 0) === (int) $order->id;
    }
}
