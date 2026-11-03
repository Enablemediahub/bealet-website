<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'ar_scale' => 'decimal:2',
            'is_active' => 'boolean',
            'featured' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('image_slot');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getImageUrlAttribute(): string
    {
        return static::assetUrl($this->main_image);
    }

    public function getGalleryUrlsAttribute(): array
    {
        $paths = [];
        $storedGallery = json_decode((string) $this->gallery_images, true);

        if (is_array($storedGallery)) {
            $paths = array_merge($paths, $storedGallery);
        }

        foreach ($this->images as $image) {
            $paths[] = $image->image_path;
        }

        $urls = [$this->image_url];
        foreach ($paths as $path) {
            $url = static::assetUrl($path);
            if (! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return array_slice($urls, 0, 5);
    }

    public static function assetUrl(?string $storedPath): string
    {
        $image = trim((string) $storedPath);

        $scheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
        if ($image !== '' && in_array($scheme, ['http', 'https'], true)) {
            return $image;
        }

        $imagePath = str_replace('\\', '/', $image);
        if (str_starts_with($imagePath, 'storage/')) {
            return asset($imagePath);
        }
        if (preg_match('#^(products|ar-models|branding|staff|founder)/#', $imagePath)) {
            return asset('storage/'.$imagePath);
        }
        if (str_starts_with($imagePath, 'assets/')) {
            $imagePath = substr($imagePath, strlen('assets/'));
        } elseif ($imagePath !== '') {
            $imagePath = 'uploads/products/'.basename($imagePath);
        }

        $segments = array_values(array_filter(
            explode('/', $imagePath),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'
        ));

        if ($segments !== []) {
            return asset('legacy-assets/'.implode('/', array_map('rawurlencode', $segments)));
        }

        return asset('images/product-placeholder.svg');
    }
}
