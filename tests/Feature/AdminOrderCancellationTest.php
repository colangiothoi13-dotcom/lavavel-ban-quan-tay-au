<?php

namespace Tests\Feature;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
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

    public function test_admin_cannot_move_a_completed_order_back_to_pending(): void
    {
        Event::fake([OrderStatusChanged::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, 'completed');

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'pending'])
            ->assertStatus(422);

        $this->assertSame('completed', $order->fresh()->status);
        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    public function test_shipping_and_completed_statuses_dispatch_order_events(): void
    {
        Event::fake([OrderStatusChanged::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $shippingOrder = $this->makeOrder($customer, 'processing');
        $completedOrder = $this->makeOrder($customer, 'shipping');

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $shippingOrder), ['status' => 'shipping'])
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $completedOrder), ['status' => 'completed'])
            ->assertRedirect();

        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $event) => $event->order->is($shippingOrder)
                && $event->previousStatus === 'processing'
                && $event->order->status === 'shipping'
        );
        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $event) => $event->order->is($completedOrder)
                && $event->previousStatus === 'shipping'
                && $event->order->status === 'completed'
        );
        Event::assertDispatchedTimes(OrderStatusChanged::class, 2);
    }

    public function test_order_status_event_sends_a_notification_to_the_customer(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, 'shipping');

        OrderStatusChanged::dispatch($order, 'processing');

        Notification::assertSentTo(
            $customer,
            OrderStatusUpdatedNotification::class,
            fn (OrderStatusUpdatedNotification $notification) => $notification->order->is($order)
        );
    }

    public function test_admin_cannot_delete_an_active_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần thử', 'base_price' => 100000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 4,
            'price' => 100000,
        ]);
        $order = $this->makeOrder($customer, 'processing');
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'Đen / M',
            'quantity' => 2,
            'price' => 100000,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.orders.destroy', $order))
            ->assertStatus(422);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
        $this->assertSame(4, $variant->fresh()->stock);
    }

    public function test_admin_can_delete_cancelled_and_seven_day_old_completed_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $cancelled = $this->makeOrder($customer, 'cancelled');
        $completed = $this->makeOrder($customer, 'completed');
        $completed->forceFill(['completed_at' => now()->subDays(7)->subMinute()])->save();

        $this->actingAs($admin)
            ->delete(route('admin.orders.destroy', $cancelled))
            ->assertRedirect(route('admin.orders.index'));
        $this->actingAs($admin)
            ->delete(route('admin.orders.destroy', $completed))
            ->assertRedirect(route('admin.orders.index'));

        $this->assertDatabaseMissing('orders', ['id' => $cancelled->id]);
        $this->assertDatabaseMissing('orders', ['id' => $completed->id]);
    }

    public function test_admin_can_delete_all_eligible_orders_without_deleting_recent_or_active_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $cancelled = $this->makeOrder($customer, 'cancelled');
        $oldCompleted = $this->makeOrder($customer, 'completed');
        $oldCompleted->forceFill(['completed_at' => now()->subDays(8)])->save();
        $recentCompleted = $this->makeOrder($customer, 'completed');
        $active = $this->makeOrder($customer, 'shipping');

        $this->actingAs($admin)
            ->delete(route('admin.orders.destroy-all'))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('status', 'Đã xóa 2 đơn hàng đủ điều kiện.');

        $this->assertDatabaseMissing('orders', ['id' => $cancelled->id]);
        $this->assertDatabaseMissing('orders', ['id' => $oldCompleted->id]);
        $this->assertDatabaseHas('orders', ['id' => $recentCompleted->id]);
        $this->assertDatabaseHas('orders', ['id' => $active->id]);
    }

    public function test_customer_cannot_delete_an_order_through_the_admin_route(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, 'pending');

        $this->actingAs($customer)
            ->delete(route('admin.orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    private function makeOrder(User $customer, string $status): Order
    {
        return $customer->orders()->create([
            'recipient_name' => $customer->name,
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => $status,
            'total' => 100000,
        ]);
    }
}
