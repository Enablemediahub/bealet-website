<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\TrackingHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OperationsAdminController extends Controller
{
    public function products(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $products = Product::query()
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.operations.products-index', compact('products'));
    }

    public function createProduct(): View
    {
        return view('admin.operations.product-form', ['product' => new Product]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $this->validatedProduct($request);
        $data['slug'] = $this->uniqueProductSlug($data['slug'] ?: $data['name']);
        $this->storeProductAssets($request, $data);
        $product = DB::transaction(function () use ($data, $request): Product {
            $product = Product::create($data);
            $this->saveGalleryImages($request, $product);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function editProduct(Product $product): View
    {
        $product->load('images');

        return view('admin.operations.product-form', compact('product'));
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validatedProduct($request, $product);
        $data['slug'] = $this->uniqueProductSlug($data['slug'] ?: $data['name'], $product);
        $oldAssets = collect([$product->main_image, $product->ar_model_2d, $product->ar_model_2d_left, $product->ar_model_2d_right, $product->ar_model_3d])
            ->merge($product->images->pluck('image_path'))
            ->filter(fn ($path): bool => is_string($path) && $path !== '' && ! str_starts_with($path, 'assets/'))
            ->all();
        $this->storeProductAssets($request, $data, $product);
        DB::transaction(function () use ($data, $request, $product): void {
            $product->update($data);
            $this->saveGalleryImages($request, $product, replace: $request->boolean('replace_gallery'));
        });
        $freshProduct = $product->fresh();
        $newAssets = collect([$freshProduct->main_image, $freshProduct->ar_model_2d, $freshProduct->ar_model_2d_left, $freshProduct->ar_model_2d_right, $freshProduct->ar_model_3d])
            ->merge($product->images()->pluck('image_path'))
            ->all();
        foreach (array_diff($oldAssets, $newAssets) as $asset) {
            if (preg_match('#^(products|ar-models)/#', (string) $asset)) {
                Storage::disk('public')->delete($asset);
            }
        }

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product updated.');
    }

    public function deactivateProduct(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);

        return back()->with('status', 'Product hidden from the storefront.');
    }

    public function inventory(): View
    {
        $products = Product::query()->orderBy('stock')->orderBy('name')->paginate(30);

        return view('admin.operations.inventory', compact('products'));
    }

    public function updateInventory(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        DB::transaction(function () use ($product, $data): void {
            Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail()->update($data);
        });

        return back()->with('status', "Inventory updated for {$product->name}.");
    }

    public function orders(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])],
        ]);
        $orders = Order::query()
            ->with('user')
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function ($builder) use ($search): void {
                    $builder->where('tracking_code', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.operations.orders-index', compact('orders'));
    }

    public function showOrder(Order $order): View
    {
        $order->load(['user', 'items', 'prescriptions']);
        $history = TrackingHistory::query()->where('order_id', $order->id)->latest('created_at')->get();

        return view('admin.operations.order-show', compact('order', 'history'));
    }

    public function updateOrder(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['processing', 'shipped', 'delivered'])],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'courier_name' => ['nullable', 'string', 'max:100'],
            'estimated_delivery' => ['nullable', 'date'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        abort_unless($order->payment_status === 'paid', 422, 'Only paid orders can move to fulfillment.');
        if ($data['status'] === 'shipped' && blank($data['tracking_number'])) {
            throw ValidationException::withMessages(['tracking_number' => 'A tracking number is required before shipping.']);
        }
        DB::transaction(function () use ($order, $data): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->payment_status === 'paid', 422, 'Only paid orders can move to fulfillment.');
            $previousStatus = $lockedOrder->status;
            $nextStatuses = ['pending' => ['processing'], 'processing' => ['processing', 'shipped'], 'shipped' => ['shipped', 'delivered'], 'delivered' => ['delivered']];
            abort_unless(in_array($data['status'], $nextStatuses[$previousStatus] ?? [], true), 422, 'Order fulfillment cannot move backwards.');
            $lockedOrder->update($data);
            if ($previousStatus !== $lockedOrder->status) {
                TrackingHistory::create([
                    'order_id' => $lockedOrder->id,
                    'status' => $lockedOrder->status,
                    'notes' => 'Status updated by an administrator.',
                ]);
            }
        });

        return back()->with('status', 'Order fulfillment details updated.');
    }

    public function customers(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $customers = User::query()->where('is_admin', false)
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            })
            ->withCount('orders')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.operations.customers', compact('customers'));
    }

    private function validatedProduct(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'brand' => ['nullable', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50'],
            'frame_target' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'sale_price' => ['nullable', 'numeric', 'gt:0', 'lte:price'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'frame_material' => ['nullable', 'string', 'max:50'],
            'frame_color' => ['nullable', 'string', 'max:50'],
            'frame_shape' => ['nullable', 'string', 'max:50'],
            'lens_width' => ['nullable', 'integer', 'min:1', 'max:200'],
            'bridge_width' => ['nullable', 'integer', 'min:1', 'max:100'],
            'temple_length' => ['nullable', 'integer', 'min:1', 'max:250'],
            'lens_type' => ['nullable', 'string', 'max:50'],
            'lens_coating' => ['nullable', 'string', 'max:100'],
            'ar_position_x' => ['nullable', 'integer', 'between:-250,250'],
            'ar_position_y' => ['nullable', 'integer', 'between:-250,250'],
            'ar_scale' => ['nullable', 'numeric', 'between:0.5,2'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gallery_uploads' => ['sometimes', 'array', 'max:4'],
            'gallery_uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'ar_model_2d' => ['nullable', 'image', 'mimes:png,webp', 'max:10240'],
            'ar_model_2d_left' => ['nullable', 'image', 'mimes:png,webp', 'max:10240'],
            'ar_model_2d_right' => ['nullable', 'image', 'mimes:png,webp', 'max:10240'],
            'ar_model_3d' => ['nullable', 'file', 'extensions:glb', 'max:30720'],
            'remove_product_image' => ['sometimes', 'boolean'],
            'remove_ar_model_2d' => ['sometimes', 'boolean'],
            'remove_ar_model_2d_left' => ['sometimes', 'boolean'],
            'remove_ar_model_2d_right' => ['sometimes', 'boolean'],
            'remove_ar_model_3d' => ['sometimes', 'boolean'],
            'replace_gallery' => ['sometimes', 'boolean'],
        ]);
        if ($request->file('ar_model_3d')) {
            $handle = fopen($request->file('ar_model_3d')->getRealPath(), 'rb');
            $signature = $handle ? fread($handle, 4) : '';
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($signature !== 'glTF') {
                throw ValidationException::withMessages(['ar_model_3d' => 'The uploaded file is not a valid binary glTF (GLB) model.']);
            }
        }

        $featured = $request->boolean('is_featured');

        return [
            ...collect($data)->except([
                'slug', 'product_image', 'gallery_uploads', 'ar_model_2d', 'ar_model_2d_left',
                'ar_model_2d_right', 'ar_model_3d', 'remove_product_image', 'remove_ar_model_2d',
                'remove_ar_model_2d_left', 'remove_ar_model_2d_right', 'remove_ar_model_3d', 'replace_gallery',
            ])->all(),
            'slug' => $data['slug'] ?? '',
            'is_active' => $request->boolean('is_active'),
            'featured' => $featured,
            'is_featured' => $featured,
            'ar_position_x' => $data['ar_position_x'] ?? 0,
            'ar_position_y' => $data['ar_position_y'] ?? 0,
            'ar_scale' => $data['ar_scale'] ?? 1,
        ];
    }

    private function uniqueProductSlug(string $value, ?Product $product = null): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->when($product, fn ($query) => $query->whereKeyNot($product->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function storeProductAssets(Request $request, array &$data, ?Product $product = null): void
    {
        foreach ([
            'product_image' => 'main_image',
            'ar_model_2d' => 'ar_model_2d',
            'ar_model_2d_left' => 'ar_model_2d_left',
            'ar_model_2d_right' => 'ar_model_2d_right',
            'ar_model_3d' => 'ar_model_3d',
        ] as $input => $column) {
            if ($request->file($input)) {
                $directory = $input === 'product_image' ? 'products' : 'ar-models';
                $stored = $request->file($input)->store($directory, 'public');
                if (! $stored) {
                    throw new \RuntimeException('The uploaded product asset could not be stored.');
                }
                $data[$column] = $stored;
            } elseif ($product && $request->boolean('remove_'.$input)) {
                $data[$column] = null;
            }
        }
    }

    private function saveGalleryImages(Request $request, Product $product, bool $replace = true): void
    {
        if (! $request->hasFile('gallery_uploads')) {
            return;
        }
        if ($replace) {
            $product->images()->where('image_path', 'like', 'products/%')->get()->each(
                fn (ProductImage $image) => Storage::disk('public')->delete($image->image_path)
            );
            $product->images()->delete();
        }
        foreach ($request->file('gallery_uploads', []) as $slot => $file) {
            $stored = $file->store('products', 'public');
            if (! $stored) {
                throw new \RuntimeException('A product gallery image could not be stored.');
            }
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $stored,
                'image_slot' => $slot + 1,
            ]);
        }
    }
}
