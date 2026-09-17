<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class AdminOrderBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_routes_require_admin_and_a_non_empty_selection(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->post(route('admin.orders.bulk.export'), [])
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.orders.bulk.export'), [])
            ->assertSessionHasErrors('order_ids');
    }

    public function test_admin_can_print_selected_orders_as_separate_a4_slips(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->makeOrder('Nguyễn Văn A', '0900000001');
        $second = $this->makeOrder('Trần Thị B', '0900000002');
        $unselected = $this->makeOrder('Khách không chọn', '0900000003');

        $first->items()->create([
            'product_name' => 'Quần tây đen',
            'variant_name' => 'Đen / M',
            'quantity' => 1,
            'price' => 350000,
        ]);
        $second->items()->create([
            'product_name' => 'Quần kaki be',
            'variant_name' => 'Be / L',
            'quantity' => 2,
            'price' => 250000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.bulk.print'), [
                'order_ids' => [$first->id, $second->id],
            ])
            ->assertOk()
            ->assertSee('class="packing-slip"', false)
            ->assertSee('@page', false)
            ->assertSee('page-break-after', false)
            ->assertSee('Nguyễn Văn A')
            ->assertSee('Trần Thị B')
            ->assertDontSee('Khách không chọn')
            ->assertSee('Quần tây đen')
            ->assertSee('Quần kaki be');

        $this->assertNotSame($first->id, $unselected->id);
    }

    public function test_admin_can_export_selected_orders_as_an_xlsx_download(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->makeOrder('Nguyễn Văn A', '0900000001');
        $second = $this->makeOrder('Trần Thị B', '0900000002');

        $first->items()->create([
            'product_name' => 'Quần tây đen',
            'variant_name' => 'Đen / M',
            'quantity' => 1,
            'price' => 350000,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.orders.bulk.export'), [
                'order_ids' => [$first->id, $second->id],
            ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $contentDisposition = $response->headers->get('Content-Disposition', '');
        $this->assertStringContainsString('don-hang-da-chon-', $contentDisposition);
        $this->assertStringContainsString('.xlsx', $contentDisposition);

        $file = $response->baseResponse->getFile();
        $this->assertNotNull($file);
        $this->assertSame('PK', substr((string) file_get_contents($file->getPathname()), 0, 2));
    }

    public function test_admin_can_mark_selected_orders_as_ghn_without_creating_a_remote_shipment(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->makeOrder('Đơn thử GHN một', '0900000011');
        $second = $this->makeOrder('Đơn thử GHN hai', '0900000012');
        $alreadyAssigned = $this->makeOrder('Đơn đã có mã', '0900000013');
        $alreadyAssigned->update([
            'ghn_order_code' => 'GHN-OLD',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.bulk.ghn'), [
                'order_ids' => [$first->id, $second->id, $alreadyAssigned->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('orders', [
            'id' => $first->id,
            'shipping_provider' => 'ghn',
            'ghn_order_code' => null,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $second->id,
            'shipping_provider' => 'ghn',
            'ghn_order_code' => null,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $alreadyAssigned->id,
            'shipping_provider' => 'ghn',
            'ghn_order_code' => 'GHN-OLD',
        ]);
        Http::assertNothingSent();
    }

    public function test_bulk_ghn_skips_cancelled_and_completed_orders_without_calling_ghn(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $active = $this->makeOrder('Đơn đang xử lý', '0900000021');
        $cancelled = $this->makeOrder('Đơn đã hủy', '0900000022');
        $cancelled->update(['status' => 'cancelled']);
        $completed = $this->makeOrder('Đơn đã hoàn thành', '0900000023');
        $completed->update(['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.orders.bulk.ghn'), [
                'order_ids' => [$active->id, $cancelled->id, $completed->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('orders', ['id' => $active->id, 'shipping_provider' => 'ghn']);
        $this->assertDatabaseHas('orders', ['id' => $cancelled->id, 'shipping_provider' => null]);
        $this->assertDatabaseHas('orders', ['id' => $completed->id, 'shipping_provider' => null]);
        Http::assertNothingSent();
    }

    public function test_bulk_archive_only_archives_orders_that_are_ready_for_retention(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cancelled = $this->makeOrder('Đơn đã hủy', '0900000031');
        $cancelled->update(['status' => 'cancelled']);
        $oldCompleted = $this->makeOrder('Đơn hoàn thành cũ', '0900000032');
        $oldCompleted->update([
            'status' => 'completed',
            'completed_at' => now()->subDays(8),
        ]);
        $recentCompleted = $this->makeOrder('Đơn hoàn thành mới', '0900000033');
        $recentCompleted->update([
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
        ]);
        $pending = $this->makeOrder('Đơn đang chờ', '0900000034');

        $this->actingAs($admin)
            ->post(route('admin.orders.bulk.archive'), [
                'order_ids' => [$cancelled->id, $oldCompleted->id, $recentCompleted->id, $pending->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($cancelled->fresh()->archived_at);
        $this->assertNotNull($oldCompleted->fresh()->archived_at);
        $this->assertNull($recentCompleted->fresh()->archived_at);
        $this->assertNull($pending->fresh()->archived_at);
    }

    private function makeOrder(string $recipientName, string $phone): Order
    {
        $customer = User::factory()->create(['name' => $recipientName]);

        return $customer->orders()->create([
            'recipient_name' => $recipientName,
            'phone' => $phone,
            'address' => 'Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 500000,
        ]);
    }
}
