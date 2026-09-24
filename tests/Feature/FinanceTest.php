<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_dashboard_and_transaction_list_use_payment_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createOrder($admin, 'paid', 175000, '2026-09-10 10:00:00');
        $order->paymentTransactions()->latest('id')->firstOrFail()->update([
            'status' => PaymentTransaction::STATUS_PAID,
            'paid_at' => '2026-09-10 10:30:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.finance.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertViewHas('collectedAmount', 175000.0)
            ->assertViewHas('paidOrderCount', 1);

        $this->actingAs($admin)
            ->get(route('admin.finance.transactions', [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
                'keyword' => $order->recipient_name,
            ]))
            ->assertOk()
            ->assertSee('175.000 đ')
            ->assertSee('Đã thanh toán');
    }

    public function test_cod_payment_update_records_paid_transaction_and_can_be_reverted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createOrder($admin, 'unpaid', 220000, now()->toDateTimeString());

        $this->actingAs($admin)
            ->patch(route('admin.finance.cod-payment', $order), ['payment_status' => 'paid'])
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'cod',
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.finance.cod-payment', $order), ['payment_status' => 'unpaid'])
            ->assertRedirect();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'cod',
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'cod',
            'status' => 'pending',
        ]);
    }

    private function createOrder(User $user, string $paymentStatus, int $total, string $createdAt): Order
    {
        $order = $user->orders()->create([
            'recipient_name' => 'Nguyễn Văn Tài Chính',
            'phone' => '0901234567',
            'address' => 'Hà Nội',
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'payment_status' => $paymentStatus,
            'status' => 'processing',
            'total' => $total,
        ]);

        $order->timestamps = false;
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $order->fresh();
    }
}
