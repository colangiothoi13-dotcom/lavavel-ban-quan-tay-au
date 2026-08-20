<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['variants', 'category']);

        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%' . $request->keyword . '%');
        }

        if ($request->filled('size')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('size', $request->size);
            });
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        if ($request->filled('sort_by')) {
            switch ($request->sort_by) {
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                case 'price_asc':
                    $query->orderBy('base_price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('base_price', 'desc');
                    break;
            }
        } else {
            $query->latest();
        }

        $products = $query->paginate(10);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::all();

        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,unisex',
            'base_price' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'attribute_colors' => 'required|array|min:1',
            'attribute_colors.*' => 'required|string|max:255',
            'attribute_color_images' => 'nullable|array',
            'attribute_color_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'attribute_sizes' => 'required|array|min:1',
            'attribute_sizes.*' => 'required|string|max:50',
            'variants' => 'required|array|min:1',
            'variants.*.color' => 'required|string',
            'variants.*.size' => 'required|string',
            'variants.*.stock' => 'required|numeric|integer|min:0|max:9223372036854775807',
        ]);

        $product = Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'gender' => $request->gender,
            'base_price' => $request->base_price,
            'image' => $this->uploadImage($request->file('image')),
        ]);

        $colorImages = [];
        foreach ($request->input('attribute_colors', []) as $index => $color) {
            $colorImages[$color] = $this->uploadImage($request->file("attribute_color_images.$index"));
        }

        foreach ($request->variants as $variantData) {
            $product->variants()->create([
                'color' => $variantData['color'],
                'size' => $variantData['size'],
                'stock' => $variantData['stock'],
                'price' => $request->base_price,
                'image' => $colorImages[$variantData['color']] ?? null,
            ]);
        }

        return redirect()->route('products.index')->with('success', 'Thêm sản phẩm thành công!');
    }

    public function show(Product $product)
    {
        $product->load(['variants', 'category']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::all();
        $product->load('variants');

        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $this->validateProductUpdate($request);

        $data = [
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'gender' => $request->gender,
            'base_price' => $request->base_price,
        ];

        if ($request->hasFile('image')) {
            $this->deleteImageIfExists($product->image);
            $data['image'] = $this->uploadImage($request->file('image'));
        }

        $product->update($data);
        $product->variants()->update(['price' => $request->base_price]);
        $this->updateExistingVariants($request, $product);
        $this->createNewVariants($request, $product);

        return redirect()->route('products.index')->with('success', 'Cập Nhập sản phẩm thành công!');
    }

    public function destroy(Product $product)
    {
        $this->deleteImageIfExists($product->image);

        foreach ($product->variants as $variant) {
            $this->deleteImageIfExists($variant->image);
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Đã xóa sản phẩm!');
    }

    private function validateProductUpdate(Request $request): void
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,unisex',
            'base_price' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'variants' => 'nullable|array',
            'variants.*.color' => 'nullable|string|max:255',
            'variants.*.size' => 'nullable|string|max:50',
            'variants.*.stock' => 'nullable|numeric|integer|min:0|max:9223372036854775807',
            'variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'new_variants' => 'nullable|array',
            'new_variants.*.color' => 'nullable|string|max:255',
            'new_variants.*.size' => 'nullable|string|max:50',
            'new_variants.*.stock' => 'nullable|numeric|integer|min:0|max:9223372036854775807',
            'new_variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
    }

    private function uploadImage($file): ?string
    {
        if (! $file || ! $file->isValid()) {
            return null;
        }

        return $file->store('products', 'public');
    }

    private function deleteImageIfExists(?string $imagePath): void
    {
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function updateExistingVariants(Request $request, Product $product): void
    {
        if (! $request->has('variants') || ! is_array($request->variants)) {
            return;
        }

        foreach ($request->variants as $variantId => $variantData) {
            $variant = $product->variants()->find($variantId);

            if (! $variant) {
                continue;
            }

            $payload = [
                'color' => $variantData['color'] ?? $variant->color,
                'size' => $variantData['size'] ?? $variant->size,
                'stock' => $variantData['stock'] ?? $variant->stock,
            ];

            if (! empty($variantData['image']) && $variantData['image'] instanceof \Illuminate\Http\UploadedFile && $variantData['image']->isValid()) {
                $this->deleteImageIfExists($variant->image);
                $payload['image'] = $this->uploadImage($variantData['image']);
            }

            $variant->update($payload);
        }
    }

    private function createNewVariants(Request $request, Product $product): void
    {
        if (! $request->has('new_variants') || ! is_array($request->new_variants)) {
            return;
        }

        foreach ($request->new_variants as $newVariantData) {
            if (empty($newVariantData['color']) || empty($newVariantData['size']) || ! isset($newVariantData['stock'])) {
                continue;
            }

            $product->variants()->create([
                'color' => $newVariantData['color'],
                'size' => $newVariantData['size'],
                'stock' => (int) $newVariantData['stock'],
                'price' => $product->base_price,
                'image' => $this->uploadImage($newVariantData['image'] ?? null),
            ]);
        }
    }
}
