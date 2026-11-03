<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TryOnController extends Controller
{
    public function index(Request $request): View
    {
        $frames = Product::active()
            ->where(function ($query): void {
                foreach (['ar_model_2d', 'ar_model_2d_left', 'ar_model_2d_right', 'ar_model_3d'] as $column) {
                    $query->orWhere(fn ($asset) => $asset->whereNotNull($column)->where($column, '<>', ''));
                }
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('featured')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'brand' => $product->brand,
                'image' => $product->image_url,
                'front' => $product->ar_model_2d || $product->ar_model_2d_left || $product->ar_model_2d_right
                    ? Product::assetUrl($product->ar_model_2d ?: ($product->ar_model_2d_left ?: $product->ar_model_2d_right))
                    : null,
                'left' => $product->ar_model_2d_left ? Product::assetUrl($product->ar_model_2d_left) : null,
                'right' => $product->ar_model_2d_right ? Product::assetUrl($product->ar_model_2d_right) : null,
                'model' => $product->ar_model_3d ? Product::assetUrl($product->ar_model_3d) : null,
                'position_x' => (int) $product->ar_position_x,
                'position_y' => (int) $product->ar_position_y,
                'scale' => (float) ($product->ar_scale ?: 1),
            ])
            ->values();
        $requestedFrame = $frames->search(fn (array $frame): bool => (int) $frame['id'] === (int) $request->query('frame'));
        $settings = SiteSetting::query()->pluck('setting_value', 'setting_key');

        return view('try-on.index', [
            'frames' => $frames,
            'selectedFrame' => $requestedFrame === false ? 0 : $requestedFrame,
            'settings' => $settings,
        ]);
    }
}
