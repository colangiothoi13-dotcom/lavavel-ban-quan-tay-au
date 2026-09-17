<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_their_pending_order_and_stock_is_returned(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần thử', 'base_price' => 100000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 100000,
        ]);
        $order = $user->orders()->create([
            'recipient_name' => $user->name,
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 200000,
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'Đen - M',
            'quantity' => 2,
            'price' => 100000,
        ]);

        $this->actingAs($user)
            ->patch(route('user.orders.cancel', $order), [
                'cancellation_reason' => 'Tôi muốn thay đổi sản phẩm.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('Tôi muốn thay đổi sản phẩm.', $order->fresh()->cancellation_reason);
        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_user_cannot_cancel_another_users_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = $owner->orders()->create([
            'recipient_name' => $owner->name,
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 100000,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('user.orders.cancel', $order))
            ->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_user_can_cancel_processing_but_not_shipping_orders(): void
    {
        $user = User::factory()->create();

        $processingOrder = $user->orders()->create([
            'recipient_name' => $user->name, 'phone' => '0900000000', 'address' => 'Hà Nội',
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'processing', 'total' => 100000,
        ]);
        $this->actingAs($user)
            ->patch(route('user.orders.cancel', $processingOrder), ['cancellation_reason' => 'Tôi không còn nhu cầu mua sản phẩm.'])
            ->assertRedirect();
        $this->assertSame('cancelled', $processingOrder->fresh()->status);

        $shippingOrder = $user->orders()->create([
            'recipient_name' => $user->name, 'phone' => '0900000000', 'address' => 'Hà Nội',
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'shipping', 'total' => 100000,
        ]);
        $this->actingAs($user)
            ->patch(route('user.orders.cancel', $shippingOrder), ['cancellation_reason' => 'Tôi không còn nhu cầu mua sản phẩm.'])
            ->assertStatus(422);
        $this->assertSame('shipping', $shippingOrder->fresh()->status);
    }

    public function test_cancelling_a_paid_pre_shipping_order_marks_refund_pending(): void
    {
        $user = User::factory()->create();
        $order = $user->orders()->create([
            'recipient_name' => $user->name, 'phone' => '0900000000', 'address' => 'Hà Nội',
            'payment_method' => 'momo', 'payment_status' => 'paid', 'status' => 'processing', 'total' => 100000,
        ]);

        $this->actingAs($user)
            ->patch(route('user.orders.cancel', $order), ['cancellation_reason' => 'Tôi không còn nhu cầu mua sản phẩm.'])
            ->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refund_pending', $order->fresh()->payment_status);
    }

    public function test_user_must_give_a_reason_before_cancelling_an_order(): void
    {
        $user = User::factory()->create();
        $order = $user->orders()->create([
            'recipient_name' => $user->name,
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 100000,
        ]);

        $this->actingAs($user)
            ->patch(route('user.orders.cancel', $order), ['cancellation_reason' => ''])
            ->assertRedirect()
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->cancellation_reason);
    }
}
