<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RevenueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_paid_orders_without_waiting_for_completion_and_excludes_cancelled_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->createOrder($admin, 'processing', 'paid', 150000, '2026-08-10 09:00:00');
        $this->createOrder($admin, 'completed', 'paid', 250000, '2026-08-10 15:00:00');
        $this->createOrder($admin, 'completed', 'unpaid', 300000, '2026-08-11 09:00:00');
        $this->createOrder($admin, 'cancelled', 'paid', 500000, '2026-08-11 10:00:00');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['from' => '2026-08-10', 'to' => '2026-08-11']))
            ->assertOk()
            ->assertViewHas('totalRevenue', 400000)
            ->assertViewHas('totalOrders', 2)
            ->assertViewHas('dailyRevenue', function (Collection $dailyRevenue): bool {
                return $dailyRevenue->pluck('total', 'date')->all() === [
                    '2026-08-10' => 400000.0,
                    '2026-08-11' => 0,
                ];
            });
    }

    private function createOrder(
        User $user,
        string $status,
        string $paymentStatus,
        int $total,
        string $createdAt,
    ): void {
        $order = $user->orders()->create([
            'recipient_name' => 'Khách hàng',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cod',
            'payment_status' => $paymentStatus,
            'status' => $status,
            'total' => $total,
        ]);

        $order->timestamps = false;
        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
