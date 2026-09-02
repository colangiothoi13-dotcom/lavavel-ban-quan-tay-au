<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPurchasePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_tab_includes_pending_and_processing_orders(): void
    {
        $user = User::factory()->create();
        $pending = $this->orderFor($user, 'pending');
        $processing = $this->orderFor($user, 'processing');
        $shipping = $this->orderFor($user, 'shipping');

        $this->actingAs($user)
            ->get(route('user.orders.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Đơn hàng #'.$pending->id)
            ->assertSee('Đơn hàng #'.$processing->id)
            ->assertDontSee('Đơn hàng #'.$shipping->id);
    }

    public function test_user_can_view_their_order_detail_but_not_another_users_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = $this->orderFor($owner, 'shipping');

        $this->actingAs($owner)
            ->get(route('user.orders.show', $order))
            ->assertOk()
            ->assertSee('TIẾN TRÌNH GIAO HÀNG')
            ->assertSee('Thông tin người nhận')
            ->assertSee('Chi tiết thanh toán');

        $this->actingAs($otherUser)
            ->get(route('user.orders.show', $order))
            ->assertForbidden();
    }

    public function test_user_can_add_available_items_from_an_old_order_back_to_cart(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user, 'completed');
        $variant = $order->items->first()->variant;
        $user->cartItems()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('user.orders.reorder', $order))
            ->assertRedirect(route('cart.index'));

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    private function orderFor(User $user, string $status): Order
    {
        $product = Product::query()->create([
            'name' => 'Quần tây thử nghiệm '.$status,
            'base_price' => 250000,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 100,
            'price' => 250000,
        ]);
        $order = $user->orders()->create([
            'recipient_name' => $user->name,
            'phone' => '0900000000',
            'address' => '123 Đường thử nghiệm, Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => $status,
            'total' => 500000,
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'Đen - M',
            'quantity' => 2,
            'price' => 250000,
        ]);

        return $order->load('items.variant');
    }
}
