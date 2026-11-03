<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredCheckoutReservations extends Command
{
    protected $signature = 'bealet:release-expired-checkout-reservations';

    protected $description = 'Release inventory held by expired unpaid checkout orders';

    public function handle(): int
    {
        $orderIds = Order::query()
            ->where('payment_status', 'pending')
            ->where('stock_reserved', true)
            ->where('reservation_expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $releasedCount = 0;
        foreach ($orderIds as $orderId) {
            $released = DB::transaction(function () use ($orderId): bool {
                $order = Order::query()->lockForUpdate()->find($orderId);
                if (
                    ! $order
                    || $order->payment_status !== 'pending'
                    || ! $order->stock_reserved
                    || ! $order->reservation_expires_at?->isPast()
                ) {
                    return false;
                }

                $items = $order->items()->orderBy('product_id')->get();
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

                $order->forceFill([
                    'payment_status' => 'failed',
                    'stock_reserved' => false,
                    'reservation_expires_at' => null,
                ])->save();

                return true;
            });

            if ($released) {
                $releasedCount++;
                Log::info('Expired checkout inventory reservation released.', ['order_id' => $orderId]);
            }
        }

        $this->info('Released '.$releasedCount.' expired checkout reservation(s).');

        return self::SUCCESS;
    }
}
