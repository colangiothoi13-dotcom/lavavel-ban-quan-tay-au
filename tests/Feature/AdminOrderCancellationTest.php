<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_an_order_returns_items_to_stock_only_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::query()->create([
            'name' => 'Sản phẩm thử',
            'base_price' => 100000,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 100000,
        ]);
        $order = $admin->orders()->create([
            'recipient_name' => 'Khách hàng',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 300000,
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'Đen / M',
            'quantity' => 3,
            'price' => 100000,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 8]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 8]);
    }

    public function test_admin_can_confirm_all_pending_orders_with_one_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['pending', 'pending', 'shipping', 'cancelled'] as $index => $status) {
            $admin->orders()->create([
                'recipient_name' => "Khách hàng {$index}",
                'phone' => '0900000000',
                'address' => 'Hà Nội',
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'status' => $status,
                'total' => 100000,
            ]);
        }

        $this->actingAs($admin)
            ->patch(route('admin.orders.confirm-all'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã xác nhận 2 đơn hàng.');

        $this->assertDatabaseCount('orders', 4);
        $this->assertSame(2, $admin->orders()->where('status', 'processing')->count());
        $this->assertSame(1, $admin->orders()->where('status', 'shipping')->count());
        $this->assertSame(1, $admin->orders()->where('status', 'cancelled')->count());
        $this->assertSame(0, $admin->orders()->where('status', 'pending')->count());
    }
}
