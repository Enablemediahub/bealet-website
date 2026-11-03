<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

class WhatsAppLink
{
    public static function contact(?string $phone, string $companyName): ?string
    {
        $normalizedPhone = self::normalizePhone($phone);
        if ($normalizedPhone === '') {
            return null;
        }

        $companyName = trim($companyName) ?: config('app.name');

        return 'https://wa.me/'.$normalizedPhone.'?text='.rawurlencode(
            'Hello '.$companyName.', I would like to make an enquiry.'
        );
    }

    public static function productInquiry(Product $product, ?string $phone, string $companyName): ?string
    {
        $normalizedPhone = self::normalizePhone($phone);
        if ($normalizedPhone === '') {
            return null;
        }

        $companyName = trim($companyName) ?: config('app.name');
        $productUrl = route('shop.show', $product->slug);
        $imageUrl = $product->image_url;
        $description = trim(strip_tags((string) $product->description));
        $message = 'Hello '.$companyName.', I am interested in '.$product->name.'. Please confirm its availability and current price.'
            ."\nProduct details: ".$productUrl
            ."\nProduct image: ".$imageUrl;

        if ($description !== '') {
            $message .= "\nDetails: ".Str::limit($description, 180);
        }

        return 'https://wa.me/'.$normalizedPhone.'?text='.rawurlencode($message);
    }

    private static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '233'.substr($digits, 1);
        }

        return $digits;
    }
}
