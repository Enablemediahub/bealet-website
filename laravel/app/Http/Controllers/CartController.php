<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $pendingOrderId = (int) $request->session()->get('checkout_order_id', 0);
        if ($pendingOrderId > 0) {
            $pendingOrder = Order::find($pendingOrderId);
            if ($pendingOrder?->payment_status === 'pending') {
                return redirect()->route('checkout.pending', $pendingOrder);
            }
        }

        $items = $this->cartQuery($request)
            ->join('products', 'products.id', '=', 'cart.product_id')
            ->select('cart.id as cart_id', 'cart.quantity', 'products.*')
            ->orderBy('cart.id')
            ->get()
            ->map(function ($item): object {
                $item->line_total_pesewas = Money::toPesewas($item->price) * (int) $item->quantity;
                $item->image_url = Product::assetUrl($item->main_image);

                return $item;
            });
        $subtotalPesewas = (int) $items->sum('line_total_pesewas');

        return view('cart.index', compact('items', 'subtotalPesewas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureNoPendingCheckout($request);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ]);
        $quantity = (int) ($data['quantity'] ?? 1);
        $product = Product::active()->findOrFail((int) $data['product_id']);

        DB::transaction(function () use ($request, $product, $quantity): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $existingItem = $this->cartQuery($request)
                ->where('product_id', $lockedProduct->id)
                ->lockForUpdate()
                ->first();
            $nextQuantity = $quantity + (int) ($existingItem->quantity ?? 0);

            if ($nextQuantity > (int) $lockedProduct->stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'There is not enough stock to add that quantity.',
                ]);
            }

            if ($existingItem) {
                DB::table('cart')->where('id', $existingItem->id)->update([
                    'quantity' => $nextQuantity,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('cart')->insert([
                    'session_id' => $request->user() ? null : $request->session()->getId(),
                    'user_id' => $request->user()?->id,
                    'product_id' => $lockedProduct->id,
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Added to your cart.');
    }

    public function update(Request $request, int $cartItem): RedirectResponse
    {
        $this->ensureNoPendingCheckout($request);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $item = $this->cartQuery($request)->where('cart.id', $cartItem)->first();
        abort_unless($item, 404);
        $product = Product::active()->findOrFail($item->product_id);

        if ((int) $data['quantity'] > (int) $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => 'There is not enough stock for that quantity.',
            ]);
        }

        $this->cartQuery($request)->where('cart.id', $cartItem)->update([
            'quantity' => (int) $data['quantity'],
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, int $cartItem): RedirectResponse
    {
        $this->ensureNoPendingCheckout($request);
        $deleted = $this->cartQuery($request)->where('cart.id', $cartItem)->delete();
        abort_unless($deleted, 404);

        return back()->with('status', 'Item removed from your cart.');
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

    private function ensureNoPendingCheckout(Request $request): void
    {
        $orderId = (int) $request->session()->get('checkout_order_id', 0);
        if ($orderId > 0 && Order::whereKey($orderId)->where('payment_status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'cart' => 'Finish or wait for the pending checkout before changing your cart.',
            ]);
        }
    }
}
