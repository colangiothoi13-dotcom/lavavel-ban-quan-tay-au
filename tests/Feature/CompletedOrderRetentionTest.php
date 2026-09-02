<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompletedOrderRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_orders_are_hidden_after_two_days_but_kept_in_database(): void
    {
        $user = User::factory()->create();
        $order = $user->orders()->create([
            'recipient_name' => 'Khách hàng',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'completed_at' => now()->subDays(2)->subMinute(),
            'total' => 100000,
        ]);

        $this->artisan('orders:archive-expired')->assertSuccessful();

        $order->refresh();
        $this->assertNotNull($order->archived_at);
        $this->actingAs($user)
            ->get(route('user.orders.index'))
            ->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_recently_completed_orders_remain_visible(): void
    {
        $user = User::factory()->create();
        $order = $user->orders()->create([
            'recipient_name' => 'Khách hàng',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
            'total' => 100000,
        ]);

        $this->artisan('orders:archive-expired')->assertSuccessful();

        $this->assertNull($order->fresh()->archived_at);
    }

    public function test_cancelled_orders_are_hidden_after_two_days_for_admin_and_user(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $user->orders()->create([
            'recipient_name' => 'Khách hàng',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'status' => 'cancelled',
            'total' => 100000,
        ]);
        DB::table('orders')->where('id', $order->id)->update(['updated_at' => now()->subDays(2)->subMinute()]);

        $this->actingAs($user)
            ->get(route('user.orders.index'))
            ->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertViewHas('orders', fn ($orders) => $orders->isEmpty());

        $this->artisan('orders:archive-expired')->assertSuccessful();
        $this->assertNotNull($order->fresh()->archived_at);
    }

    public function test_completed_and_cancelled_orders_are_sorted_below_active_orders_for_admin_and_user(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $completed = $this->makeOrder($user, 'completed');
        $pending = $this->makeOrder($user, 'pending');
        $cancelled = $this->makeOrder($user, 'cancelled');
        $shipping = $this->makeOrder($user, 'shipping');
        $expectedIds = [$shipping->id, $pending->id, $cancelled->id, $completed->id];

        $this->actingAs($user)
            ->get(route('user.orders.index'))
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === $expectedIds);
        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === $expectedIds);
    }

    private function makeOrder(User $user, string $status): Order
    {
        return $user->orders()->create([
            'recipient_name' => 'Khách hàng',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cash',
            'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
            'total' => 100000,
        ]);
    }
}
