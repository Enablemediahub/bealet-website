<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ContentAsset
{
    public static function url(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return asset('images/product-placeholder.svg');
        }

        if (in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }
        if (preg_match('#^(blog|reviews|founder|branding|staff|products|ar-models)/#', $normalized)) {
            return asset('storage/'.$normalized);
        }

        return Product::assetUrl($normalized);
    }

    public static function blogUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return asset('images/product-placeholder.svg');
        }

        if (in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $storagePath = preg_replace('#^storage/#', '', $normalized);
        if ($storagePath !== $normalized || str_starts_with($normalized, 'blog/')) {
            if (Storage::disk('public')->exists($storagePath)) {
                return asset('storage/'.$storagePath);
            }
        }

        $basename = basename($normalized);
        if ($basename !== '' && $basename !== '.' && $basename !== '..') {
            $legacyBlogPath = 'legacy-assets/uploads/blog/'.rawurlencode($basename);
            if (is_file(public_path('legacy-assets/uploads/blog/'.$basename))) {
                return asset($legacyBlogPath);
            }

            $legacyImagePath = 'legacy-assets/images/'.rawurlencode($basename);
            if (is_file(public_path('legacy-assets/images/'.$basename))) {
                return asset($legacyImagePath);
            }
        }

        return asset('images/product-placeholder.svg');
    }
}
