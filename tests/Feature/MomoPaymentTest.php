<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\MomoPaymentAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_checkout_keeps_cash_method(): void
    {
        config(['services.ghn' => [
            'base_url' => 'https://ghn.test/api',
            'token' => 'test-token',
            'shop_id' => '123',
            'verify_ssl' => false,
            'from_district_id' => 1450,
            'service_type_id' => 2,
            'default_weight' => 500,
            'default_length' => 20,
            'default_width' => 15,
            'default_height' => 10,
            'timeout' => 10,
        ]]);
        Http::fake([
            'https://ghn.test/*' => Http::response(['code' => 200, 'data' => ['total' => 30000]]),
            'https://momo.test/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://momo.test/pay']),
        ]);

        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây test MoMo', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->post(route('checkout.place'), [
                'selected_items' => [$variant->id],
                'name' => 'Nguyễn Văn A',
                'phone' => '0900000000',
                'address' => '41A, Phường Phú Diễn, Hà Nội',
                'payment_method' => 'cash',
                'ghn_district_id' => 1485,
                'ghn_ward_code' => '1A0213',
            ])
            ->assertRedirect(route('shop.home'));

        $this->assertSame(Order::PAYMENT_METHOD_CASH, Order::query()->firstOrFail()->payment_method);
    }

    public function test_checkout_without_selected_items_uses_the_entire_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây toàn bộ giỏ hàng', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Quần tây toàn bộ giỏ hàng');
    }

    public function test_checkout_offers_all_payment_methods(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây lựa chọn MoMo', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->get(route('checkout', ['selected_items' => [$variant->id]]))
            ->assertOk()
            ->assertSee('Tiền mặt khi nhận hàng')
            ->assertSee('Chuyển khoản nội địa')
            ->assertSee('Chuyển khoản quốc tế')
            ->assertSee('MoMo (Quét mã/Ví)')
            ->assertSee('MoMo (Thẻ ATM nội địa)')
            ->assertSee('MoMo (Visa/Mastercard/JCB quốc tế)');
    }

    public function test_checkout_ignores_blank_selected_item_values(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây lựa chọn rỗng', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->get(route('checkout', ['selected_items' => ['']]))
            ->assertOk()
            ->assertSee('Quần tây lựa chọn rỗng');
    }

    #[DataProvider('transferPaymentMethods')]
    public function test_checkout_keeps_transfer_methods(string $paymentMethod): void
    {
        config(['services.ghn' => [
            'base_url' => 'https://ghn.test/api',
            'token' => 'test-token',
            'shop_id' => '123',
            'verify_ssl' => false,
            'from_district_id' => 1450,
            'service_type_id' => 2,
            'default_weight' => 500,
            'default_length' => 20,
            'default_width' => 15,
            'default_height' => 10,
            'timeout' => 10,
        ]]);
        Http::fake([
            'https://ghn.test/*' => Http::response(['code' => 200, 'data' => ['total' => 30000]]),
        ]);

        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây chuyển khoản', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->post(route('checkout.place'), [
                'selected_items' => [$variant->id],
                'name' => 'Nguyễn Văn A',
                'phone' => '0900000000',
                'address' => '41A, Phường Phú Diễn, Hà Nội',
                'payment_method' => $paymentMethod,
                'ghn_district_id' => 1485,
                'ghn_ward_code' => '1A0213',
            ])
            ->assertRedirect(route('shop.home'));

        $this->assertSame($paymentMethod, Order::query()->firstOrFail()->payment_method);
    }

    public static function transferPaymentMethods(): array
    {
        return [
            [Order::PAYMENT_METHOD_BANK_TRANSFER],
            [Order::PAYMENT_METHOD_INTERNATIONAL_TRANSFER],
        ];
    }

    public function test_failed_momo_checkout_redirects_to_order_detail(): void
    {
        config(['services.ghn' => [
            'base_url' => 'https://ghn.test/api',
            'token' => 'test-token',
            'shop_id' => '123',
            'verify_ssl' => false,
            'from_district_id' => 1450,
            'service_type_id' => 2,
            'default_weight' => 500,
            'default_length' => 20,
            'default_width' => 15,
            'default_height' => 10,
            'timeout' => 10,
        ]]);
        Http::fake([
            'https://ghn.test/*' => Http::response(['code' => 200, 'data' => ['total' => 30000]]),
            'https://momo.test/*' => Http::response([], 415),
        ]);

        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây MoMo lỗi', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->post(route('checkout.place'), [
            'selected_items' => [$variant->id],
            'name' => 'Nguyễn Văn A',
            'phone' => '0900000000',
            'address' => '41A, Phường Phú Diễn, Hà Nội',
            'payment_method' => 'momo',
            'ghn_district_id' => 1485,
            'ghn_ward_code' => '1A0213',
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('user.orders.show', $order));
    }

    public function test_checkout_submission_without_selected_items_uses_entire_cart_for_momo(): void
    {
        config(['services.ghn' => [
            'base_url' => 'https://ghn.test/api',
            'token' => 'test-token',
            'shop_id' => '123',
            'verify_ssl' => false,
            'from_district_id' => 1450,
            'service_type_id' => 2,
            'default_weight' => 500,
            'default_length' => 20,
            'default_width' => 15,
            'default_height' => 10,
            'timeout' => 10,
        ]]);
        Http::fake([
            'https://ghn.test/*' => Http::response(['code' => 200, 'data' => ['total' => 30000]]),
            'https://momo.test/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://momo.test/pay']),
        ]);

        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây gửi MoMo', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->post(route('checkout.place'), [
                'name' => 'Nguyễn Văn A',
                'phone' => '0900000000',
                'address' => '41A, Phường Phú Diễn, Hà Nội',
                'payment_method' => 'momo',
                'ghn_district_id' => 1485,
                'ghn_ward_code' => '1A0213',
            ])
            ->assertRedirect('https://momo.test/pay');

        $this->assertSame(Order::PAYMENT_METHOD_MOMO, Order::query()->firstOrFail()->payment_method);
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
            === ['order_id' => (string) $order->id]
            && $request->isJson());
    }

    public function test_create_payment_supports_the_endpoint_configuration_key(): void
    {
        Http::fake([
            'https://momo.test/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://momo.test/pay'], 200),
        ]);
        $order = $this->makeOrder();
        $service = new \App\Services\Payments\MomoPaymentService([
            'partner_code' => 'MOMO',
            'access_key' => 'access',
            'secret_key' => 'secret',
            'endpoint' => 'https://momo.test/v2/gateway/api/create',
        ]);

        $response = $service->createPayment($order, 'https://shop.test/result', 'https://shop.test/ipn');

        $this->assertSame('https://momo.test/pay', $response['pay_url']);
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
        $this->actingAs($admin)->get(route('admin.orders.show', $order))
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
