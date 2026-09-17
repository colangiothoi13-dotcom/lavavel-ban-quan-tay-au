<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_order_index_links_to_detail_and_keeps_cancel_action_off_the_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['name' => 'Hải Phạm']);
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

        $response = $this->actingAs($admin)->get(route('admin.orders.index'));

        $response->assertOk()
            ->assertSee('Mã đơn hàng')
            ->assertSee('Ngày tạo đơn')
            ->assertSee('Tên khách hàng')
            ->assertSee('DH'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('GHN')
            ->assertSee('Chưa có vận đơn')
            ->assertSee(url('/admin/orders/'.$order->id), false)
            ->assertSee('id="bulk-order-form"', false)
            ->assertSee('data-order-url="'.route('admin.orders.show', $order).'"', false)
            ->assertSee(route('admin.orders.bulk.print'), false)
            ->assertSee(route('admin.orders.bulk.export'), false)
            ->assertSee(route('admin.orders.bulk.ghn'), false)
            ->assertSee(route('admin.orders.bulk.archive'), false)
            ->assertDontSee('Hủy đơn')
            ->assertDontSee('Thao tác');
    }

    public function test_admin_order_index_filters_by_customer_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $matching = User::factory()->create(['name' => 'Khách phù hợp']);
        $other = User::factory()->create(['name' => 'Khách khác']);

        $matchingOrder = $this->makeOrder($matching, '0345378017');
        $otherOrder = $this->makeOrder($other, '0900000000');

        $response = $this->actingAs($admin)->get(route('admin.orders.index', [
            'keyword' => '0345378017',
        ]));

        $response->assertOk()
            ->assertSee('DH'.str_pad((string) $matchingOrder->id, 6, '0', STR_PAD_LEFT))
            ->assertDontSee('DH'.str_pad((string) $otherOrder->id, 6, '0', STR_PAD_LEFT));
    }

    private function makeOrder(User $customer, string $phone): Order
    {
        return $customer->orders()->create([
            'recipient_name' => $customer->name,
            'phone' => $phone,
            'address' => 'Hải Phòng',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 410500,
        ]);
    }
}
