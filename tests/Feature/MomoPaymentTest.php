<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\MomoPaymentAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MomoPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.momo' => [
            'partner_code' => 'MOMO',
            'access_key' => 'access',
            'secret_key' => 'secret',
            'base_url' => 'https://momo.test',
            'create_endpoint' => '/v2/gateway/api/create',
            'timeout' => 15,
            'payment_timeout' => 30,
        ]]);
    }

    public function test_create_payment_persists_the_request_reference(): void
    {
        Http::fake([
            'https://momo.test/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://momo.test/pay'], 200),
        ]);
        $order = $this->makeOrder();

        $response = app(\App\Services\Payments\MomoPaymentService::class)
            ->createPayment($order, 'https://shop.test/result', 'https://shop.test/ipn');

        $order->refresh();
        $this->assertSame($response['order_id'], $order->momo_order_id);
        $this->assertSame($response['request_id'], $order->momo_request_id);
        $this->assertNotNull($order->payment_expires_at);
        Http::assertSent(fn ($request) => json_decode(base64_decode($request['extraData'], true), true)
            === ['order_id' => (string) $order->id]);
    }

    public function test_ipn_marks_a_matching_payment_paid_and_is_idempotent(): void
    {
        $order = $this->makeOrder();
        $payload = $this->momoPayload($order);

        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('TRANS-1', $order->fresh()->momo_transaction_id);

        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_return_callback_uses_the_same_reconciliation_rules_as_ipn(): void
    {
        $order = $this->makeOrder();
        $payload = $this->momoPayload($order);

        $this->get(route('momo.result', $payload))
            ->assertOk()
            ->assertSee('Thanh toán MoMo thành công')
            ->assertSee('Đã thanh toán')
            ->assertDontSee('Thanh toán lại với MoMo');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('TRANS-1', $order->fresh()->momo_transaction_id);
    }

    public function test_callback_for_an_older_payment_link_is_still_reconciled(): void
    {
        $order = $this->makeOrder();
        $firstOrderId = $order->momo_order_id;
        $firstRequestId = $order->momo_request_id;
        $order->momoPaymentAttempts()->create([
            'momo_order_id' => 'ORDER-'.$order->id.'-REQUEST-2',
            'request_id' => 'REQUEST-2',
            'amount' => 100000,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);
        $order->update([
            'momo_order_id' => 'ORDER-'.$order->id.'-REQUEST-2',
            'momo_request_id' => 'REQUEST-2',
        ]);

        $payload = $this->momoPayload($order, [
            'orderId' => $firstOrderId,
            'requestId' => $firstRequestId,
        ]);

        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->momoPaymentAttempts()->where('momo_order_id', $firstOrderId)->value('status'));
    }

    public function test_second_successful_link_is_refunded_without_downgrading_the_order_payment(): void
    {
        $order = $this->makeOrder();
        $firstOrderId = $order->momo_order_id;
        $firstRequestId = $order->momo_request_id;
        $secondOrderId = 'ORDER-'.$order->id.'-REQUEST-2';
        $order->momoPaymentAttempts()->create([
            'momo_order_id' => $secondOrderId,
            'request_id' => 'REQUEST-2',
            'amount' => 100000,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->postJson(route('momo.ipn'), $this->momoPayload($order, [
            'orderId' => $firstOrderId,
            'requestId' => $firstRequestId,
        ]))->assertNoContent();

        $order->update(['momo_order_id' => $secondOrderId, 'momo_request_id' => 'REQUEST-2']);
        $secondPayload = $this->momoPayload($order, ['transId' => 'TRANS-2']);
        $this->postJson(route('momo.ipn'), $secondPayload)->assertNoContent();

        $this->assertSame('paid_refund_pending', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->momoPaymentAttempts()->where('momo_order_id', $firstOrderId)->value('status'));
        $this->assertSame('refund_pending', $order->momoPaymentAttempts()->where('momo_order_id', $secondOrderId)->value('status'));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertSee('Xác nhận đã hoàn tiền');
        $this->get(route('admin.reports.index'))
            ->assertViewHas('totalRevenue', fn ($total) => (float) $total === 100000.0);
        $this->actingAs($admin)
            ->patch(route('admin.orders.cancellation-settlement', $order), ['action' => 'refund'])
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->momoPaymentAttempts()->where('momo_order_id', $secondOrderId)->value('status'));
        $this->postJson(route('momo.ipn'), $secondPayload)->assertNoContent();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_a_refunded_attempt_cannot_become_refund_pending_again(): void
    {
        $order = $this->makeOrder(['status' => 'cancelled', 'payment_expires_at' => now()->subMinute()]);
        $payload = $this->momoPayload($order);
        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.orders.cancellation-settlement', $order), ['action' => 'refund'])
            ->assertRedirect();

        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->momoPaymentAttempts()->first()->status);
    }

    public function test_ipn_rejects_a_callback_with_a_different_amount(): void
    {
        $order = $this->makeOrder();
        $payload = $this->momoPayload($order, ['amount' => 999]);

        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->momo_transaction_id);
    }

    public function test_late_successful_ipn_is_marked_for_refund_instead_of_being_ignored(): void
    {
        $order = $this->makeOrder([
            'status' => 'cancelled',
            'payment_expires_at' => now()->subMinute(),
        ]);
        $payload = $this->momoPayload($order);

        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();

        $this->assertSame('refund_pending', $order->fresh()->payment_status);
        $this->assertSame('TRANS-1', $order->fresh()->momo_transaction_id);
    }

    public function test_late_callback_cancels_an_active_order_and_returns_it_to_the_refund_workflow(): void
    {
        $order = $this->makeOrder(['payment_expires_at' => now()->subMinute()]);
        $product = Product::query()->create(['name' => 'Test product', 'base_price' => 100000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id, 'color' => 'Black', 'size' => 'M',
            'stock' => 5, 'price' => 100000,
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id, 'product_name' => $product->name,
            'variant_name' => 'Black / M', 'quantity' => 1, 'price' => 100000,
        ]);

        $this->postJson(route('momo.ipn'), $this->momoPayload($order))->assertNoContent();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refund_pending', $order->fresh()->payment_status);
        $this->assertSame('returned', $order->fresh()->stock_return_status);
        $this->assertSame(6, $variant->fresh()->stock);
        $this->get(route('momo.result', $this->momoPayload($order)))
            ->assertOk()->assertViewHas('status', 'refund_pending')
            ->assertSee('Chờ hoàn tiền')->assertDontSee('Thanh toán lại với MoMo');
        $this->artisan('orders:cancel-expired-payments')->assertSuccessful();
        $this->assertSame(6, $variant->fresh()->stock);
    }

    public function test_refund_finishes_even_when_another_link_was_never_paid(): void
    {
        $order = $this->makeOrder(['payment_expires_at' => now()->subMinute()]);
        $order->momoPaymentAttempts()->create([
            'momo_order_id' => 'UNUSED-LINK', 'request_id' => 'UNUSED-REQUEST',
            'amount' => 100000, 'status' => 'pending',
        ]);
        $payload = $this->momoPayload($order);
        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.orders.cancellation-settlement', $order), ['action' => 'refund'])
            ->assertRedirect();
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->actingAs($order->user)->get(route('momo.result', $payload))
            ->assertOk()->assertViewHas('status', 'refunded')
            ->assertDontSee('Thanh toán lại với MoMo');
    }

    public function test_cancelled_unpaid_order_does_not_offer_retry_on_result_page(): void
    {
        $order = $this->makeOrder(['status' => 'cancelled']);
        $this->get(route('momo.result', $this->momoPayload($order, ['resultCode' => 1006])))
            ->assertOk()->assertDontSee('Thanh toán lại với MoMo');
    }

    public function test_old_momo_link_is_excess_after_cash_payment(): void
    {
        $order = $this->makeCashPaidOrder();
        $payload = $this->momoPayload($order);
        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();
        $this->assertSame('paid_refund_pending', $order->fresh()->payment_status);
        $this->assertSame('cash', $order->fresh()->payment_method);
        $this->assertSame('refund_pending', $order->momoPaymentAttempts()->first()->status);
        $this->patch(route('admin.orders.cancellation-settlement', $order), ['action' => 'refund'])
            ->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->postJson(route('momo.ipn'), $payload)->assertNoContent();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_cash_refund_is_not_blocked_by_unused_momo_link(): void
    {
        $order = $this->makeCashPaidOrder();
        $this->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertRedirect();
        $this->patch(route('admin.orders.cancellation-settlement', $order), ['action' => 'refund'])
            ->assertRedirect();
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    private function makeCashPaidOrder(): Order
    {
        $order = $this->makeOrder(['status' => 'processing']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('admin.orders.status', $order), ['status' => 'shipping'])->assertRedirect();
        $this->patch(route('admin.orders.payment', $order), ['payment_status' => 'paid'])->assertRedirect();
        // The callback still carries the original link identifiers.
        return $order;
    }

    private function makeOrder(array $attributes = []): Order
    {
        $user = User::factory()->create();

        $order = $user->orders()->create(array_merge([
            'recipient_name' => $user->name,
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => Order::PAYMENT_METHOD_MOMO,
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'total' => 100000,
            'momo_order_id' => 'ORDER-1-REQUEST-1',
            'momo_request_id' => 'REQUEST-1',
            'payment_expires_at' => now()->addMinutes(30),
        ], $attributes));
        MomoPaymentAttempt::query()->create([
            'order_id' => $order->id,
            'momo_order_id' => $order->momo_order_id,
            'request_id' => $order->momo_request_id,
            'amount' => 100000,
            'status' => 'pending',
            'expires_at' => $order->payment_expires_at,
        ]);

        return $order;
    }

    private function momoPayload(Order $order, array $overrides = []): array
    {
        $payload = array_merge([
            'partnerCode' => 'MOMO',
            'requestId' => $order->momo_request_id,
            'amount' => 100000,
            'orderId' => $order->momo_order_id,
            'orderInfo' => 'Thanh toán đơn hàng #'.$order->id,
            'orderType' => 'momo_wallet',
            'transId' => 'TRANS-1',
            'resultCode' => 0,
            'message' => 'Successful.',
            'payType' => 'webApp',
            'responseTime' => 1725000000000,
            'extraData' => base64_encode(json_encode(['order_id' => (string) $order->id], JSON_THROW_ON_ERROR)),
        ], $overrides);
        $payload['signature'] = hash_hmac('sha256', implode('&', [
            'accessKey=access',
            'amount='.$payload['amount'],
            'extraData='.$payload['extraData'],
            'message='.$payload['message'],
            'orderId='.$payload['orderId'],
            'orderInfo='.$payload['orderInfo'],
            'orderType='.$payload['orderType'],
            'partnerCode='.$payload['partnerCode'],
            'payType='.$payload['payType'],
            'requestId='.$payload['requestId'],
            'responseTime='.$payload['responseTime'],
            'resultCode='.$payload['resultCode'],
            'transId='.$payload['transId'],
        ]), 'secret');

        return $payload;
    }
}
