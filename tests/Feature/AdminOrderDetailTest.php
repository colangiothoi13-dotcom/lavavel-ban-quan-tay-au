<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_order_detail_and_see_cancel_action_there(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['name' => 'Hải Phạm']);
        $product = Product::query()->create(['name' => 'Quần jean nam', 'base_price' => 410500]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Xanh',
            'size' => 'M',
            'stock' => 5,
            'price' => 410500,
        ]);
        $order = $customer->orders()->create([
            'recipient_name' => $customer->name,
            'phone' => '0345378017',
            'address' => 'Hải Phòng',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 410500,
            'shipping_provider' => 'ghn',
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'Xanh / M',
            'quantity' => 1,
            'price' => 410500,
        ]);

        $response = $this->actingAs($admin)->get(url('/admin/orders/'.$order->id));

        $response->assertOk()
            ->assertSee('Chi tiết đơn hàng')
            ->assertSee('Quần jean nam')
            ->assertSee('Hải Phòng')
            ->assertSee('GHN')
            ->assertSee('Chưa có')
            ->assertSee('Hủy đơn')
            ->assertSee(route('admin.orders.status', $order), false)
            ->assertSee('value="cancelled"', false);
    }

    public function test_regular_user_cannot_open_admin_order_detail(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer);

        $this->actingAs($customer)
            ->get(url('/admin/orders/'.$order->id))
            ->assertForbidden();
    }

    public function test_admin_can_cancel_order_from_detail_flow_with_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), [
                'status' => 'cancelled',
                'cancellation_reason' => 'Khách yêu cầu hủy đơn.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Khách yêu cầu hủy đơn.',
        ]);
    }

    private function makeOrder(User $customer): Order
    {
        return $customer->orders()->create([
            'recipient_name' => $customer->name,
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 100000,
        ]);
    }
}
