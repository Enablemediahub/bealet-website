<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    /**
     * @return array{authorization_url: string, access_code: string, reference: string}
     *
     * @throws ConnectionException
     */
    public function initialize(Order $order): array
    {
        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            throw new RuntimeException('Paystack is not configured.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($secret)
            ->timeout(15)
            ->post(rtrim((string) config('services.paystack.base_url'), '/').'/transaction/initialize', [
                'email' => $order->guest_email ?? $order->user?->email,
                'amount' => Money::toPesewas($order->total_amount),
                'currency' => (string) config('services.paystack.currency', 'GHS'),
                'callback_url' => route('payments.paystack.callback'),
                'metadata' => [
                    'order_id' => $order->id,
                    'tracking_code' => $order->tracking_code,
                ],
            ]);

        $response->throw();
        $payload = $response->json();
        $authorizationUrl = (string) data_get($payload, 'data.authorization_url', '');
        $host = strtolower((string) parse_url($authorizationUrl, PHP_URL_HOST));
        if (
            data_get($payload, 'status') !== true
            || ! filter_var($authorizationUrl, FILTER_VALIDATE_URL)
            || $host !== 'checkout.paystack.com'
        ) {
            throw new RuntimeException('Paystack did not return a valid checkout session.');
        }

        $reference = (string) data_get($payload, 'data.reference', '');
        $accessCode = (string) data_get($payload, 'data.access_code', '');
        if ($reference === '' || $accessCode === '') {
            throw new RuntimeException('Paystack returned an incomplete checkout session.');
        }

        return [
            'authorization_url' => $authorizationUrl,
            'access_code' => $accessCode,
            'reference' => $reference,
        ];
    }

    /**
     * @throws ConnectionException
     */
    public function verify(string $reference): array
    {
        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            throw new RuntimeException('Paystack is not configured.');
        }

        $url = rtrim((string) config('services.paystack.base_url'), '/').'/transaction/verify/'.rawurlencode($reference);
        $response = Http::acceptJson()
            ->withToken($secret)
            ->timeout(15)
            ->get($url);

        $response->throw();
        $payload = $response->json();
        if (data_get($payload, 'status') !== true || ! is_array(data_get($payload, 'data'))) {
            throw new RuntimeException('Paystack could not verify this transaction.');
        }

        return $payload['data'];
    }
}
