<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_order_items_show_variant_image_with_product_image_as_fallback(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Quan tay co anh',
            'base_price' => 350000,
            'image' => 'products/product-fallback.jpg',
        ]);
        $variantWithImage = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Den',
            'size' => 'M',
            'stock' => 10,
            'price' => 350000,
            'image' => 'variants/black-m.jpg',
        ]);
        $variantWithoutImage = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Xam',
            'size' => 'L',
            'stock' => 10,
            'price' => 350000,
            'image' => null,
        ]);
        $order = $customer->orders()->create([
            'recipient_name' => $customer->name,
            'phone' => '0900000000',
            'address' => 'Ha Noi',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 700000,
        ]);
        $order->items()->createMany([
            [
                'product_variant_id' => $variantWithImage->id,
                'product_name' => 'Quan tay co anh',
                'variant_name' => 'Den - M',
                'quantity' => 1,
                'price' => 350000,
            ],
            [
                'product_variant_id' => $variantWithoutImage->id,
                'product_name' => 'Quan tay co anh',
                'variant_name' => 'Xam - L',
                'quantity' => 1,
                'price' => 350000,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee(asset('storage/variants/black-m.jpg'), false)
            ->assertSee(asset('storage/products/product-fallback.jpg'), false);
    }
}
