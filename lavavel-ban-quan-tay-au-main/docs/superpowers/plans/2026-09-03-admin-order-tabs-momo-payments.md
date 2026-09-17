# Admin Order Tabs and MoMo Payments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the admin order-status dropdown with counted tabs and add secure MoMo checkout, retry, IPN confirmation, and automatic full refunds.

**Architecture:** Keep MoMo protocol code in a focused service and persist every payment/refund attempt in `payment_transactions`. Controllers authorize HTTP entry points, an orchestration service coordinates order state, and a queued job handles refunds outside database transactions. Admin and customer views consume explicit order/payment states.

**Tech Stack:** PHP 8.2, Laravel 12, Blade, Eloquent, Laravel HTTP Client, database queues, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-09-03-admin-order-tabs-momo-payments-design.md`

## Global Constraints

- Use MoMo Payment Gateway V2 directly; do not add a third-party package.
- Use `requestType=captureWallet` for one-time wallet payment creation.
- Never mark an order paid from browser redirect parameters.
- Verify IPN signatures, partner code, request identity, merchant order identity, and persisted amount before state changes.
- Support full-order refunds only.
- Keep cash and bank-transfer behavior working.
- Keep MoMo disabled until `MOMO_ENABLED` and all credentials are configured.
- Never store or log MoMo access/secret keys or canonical signature strings.
- Make payment IPNs, refund attempts, and stock restoration idempotent.
- Use `php vendor/bin/phpunit --do-not-cache-result` because `php artisan test` cannot spawn reliably from this Windows OneDrive workspace.

---

### Task 1: Admin Order Status Tabs

**Files:**
- Create: `tests/Feature/AdminOrderTabsTest.php`
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `resources/views/admin/orders/index.blade.php`

**Interfaces:**
- Consumes: existing `GET admin.orders.index` query parameters `status` and `payment_status`.
- Produces: `$statusCounts`, an integer map keyed by `all`, `pending`, `processing`, `shipping`, `completed`, and `cancelled`.

- [ ] **Step 1: Write failing tab behavior tests**

Create orders in every status, then assert that the shipping tab is active, only shipping orders render, each tab exposes its count, and `payment_status=paid` remains in tab URLs.

```php
public function test_admin_uses_counted_status_tabs_that_preserve_payment_filter(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create();
    $this->makeOrder($customer, 'pending', 'paid');
    $shipping = $this->makeOrder($customer, 'shipping', 'paid');
    $this->makeOrder($customer, 'shipping', 'unpaid');

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['status' => 'shipping', 'payment_status' => 'paid']))
        ->assertOk()
        ->assertSee('data-order-status-tabs', false)
        ->assertSee('data-status-tab="shipping" aria-current="page"', false)
        ->assertSee('Đơn #'.$shipping->id)
        ->assertSee(route('admin.orders.index', ['status' => 'pending', 'payment_status' => 'paid']), false)
        ->assertDontSee('name="status"', false);
}
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\AdminOrderTabsTest.php
```

Expected: failure because `data-order-status-tabs` and `$statusCounts` do not exist and the status dropdown still renders.

- [ ] **Step 3: Calculate global tab counts and filter the selected tab**

In `AdminOrderController::index`, validate the status key, calculate counts independently of the payment filter, and keep `payment_status` filtering on the order query.

```php
$allowedStatuses = ['pending', 'processing', 'shipping', 'completed', 'cancelled'];
$status = in_array($request->string('status')->toString(), $allowedStatuses, true)
    ? $request->string('status')->toString()
    : '';
$rawCounts = Order::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
$statusCounts = collect($allowedStatuses)
    ->mapWithKeys(fn (string $key): array => [$key => (int) $rawCounts->get($key, 0)])
    ->prepend((int) $rawCounts->sum(), 'all');
```

- [ ] **Step 4: Replace status select with accessible tab links**

Render a `<nav data-order-status-tabs>` before a payment-only filter. Use `aria-current="page"` on the current link and `request()->filled('payment_status')` to preserve the payment query in every link. Add responsive CSS so tabs wrap on narrow screens.

- [ ] **Step 5: Run focused and existing admin-order tests**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\AdminOrderTabsTest.php tests\Feature\AdminOrderCancellationTest.php tests\Feature\AdminOrderProductImageTest.php
```

Expected: all tests pass.

- [ ] **Step 6: Commit the tab feature**

```powershell
git add app/Http/Controllers/AdminOrderController.php resources/views/admin/orders/index.blade.php tests/Feature/AdminOrderTabsTest.php
git commit -m "feat: add admin order status tabs"
```

---

### Task 2: Payment Transaction Persistence and Order States

**Files:**
- Create: `database/migrations/2026_09_03_030000_create_payment_transactions_table.php`
- Create: `database/migrations/2026_09_03_031000_add_momo_payment_dates_to_orders_table.php`
- Create: `app/Models/PaymentTransaction.php`
- Create: `tests/Feature/PaymentTransactionPersistenceTest.php`
- Modify: `app/Models/Order.php`

**Interfaces:**
- Produces: `Order::paymentTransactions(): HasMany`, `Order::successfulMomoPayment(): ?PaymentTransaction`, and `PaymentTransaction::parent(): BelongsTo`.
- Produces fields defined in the approved spec, including `kind`, `merchant_order_id`, `request_id`, `provider_transaction_id`, `amount`, `status`, `result_code`, and safe JSON response data.

- [ ] **Step 1: Write failing persistence and relationship tests**

```php
public function test_order_keeps_multiple_momo_attempts_and_finds_the_successful_payment(): void
{
    $order = $this->makeOrder(['payment_method' => 'momo']);
    $order->paymentTransactions()->create($this->attempt('pay-1', 'failed'));
    $successful = $order->paymentTransactions()->create(
        $this->attempt('pay-2', 'succeeded') + ['provider_transaction_id' => '311445566']
    );

    $this->assertCount(2, $order->paymentTransactions);
    $this->assertTrue($successful->is($order->successfulMomoPayment()));
}
```

The helper returns literal values for provider `momo`, kind `payment`, distinct request IDs, and the order total.

- [ ] **Step 2: Run the persistence test and verify RED**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\PaymentTransactionPersistenceTest.php
```

Expected: failure because the table, model, and relationship do not exist.

- [ ] **Step 3: Add migrations with duplicate-protection constraints**

Create `payment_transactions` with unique indexes on `merchant_order_id` and `request_id`, indexed provider/kind/status fields, a cascading order foreign key, and a nullable self-reference. Add nullable `paid_at` and `refunded_at` timestamps to orders.

```php
$table->foreignId('order_id')->constrained()->cascadeOnDelete();
$table->foreignId('parent_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
$table->string('provider', 30);
$table->string('kind', 20);
$table->string('merchant_order_id', 100)->unique();
$table->string('request_id', 100)->unique();
$table->string('provider_transaction_id', 100)->nullable()->index();
$table->unsignedBigInteger('amount');
$table->string('status', 30);
$table->integer('result_code')->nullable();
$table->string('message', 500)->nullable();
$table->json('response_payload')->nullable();
```

- [ ] **Step 4: Add focused Eloquent models and casts**

`PaymentTransaction` exposes only production relationships and casts `amount` to integer and `response_payload` to array. Extend `Order::$fillable`, timestamp casts, and relationship methods.

- [ ] **Step 5: Run persistence tests**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\PaymentTransactionPersistenceTest.php
```

Expected: pass.

- [ ] **Step 6: Commit the persistence layer**

```powershell
git add app/Models/Order.php app/Models/PaymentTransaction.php database/migrations/2026_09_03_030000_create_payment_transactions_table.php database/migrations/2026_09_03_031000_add_momo_payment_dates_to_orders_table.php tests/Feature/PaymentTransactionPersistenceTest.php
git commit -m "feat: persist payment and refund attempts"
```

---

### Task 3: MoMo Protocol Service and Configuration

**Files:**
- Create: `app/Services/Payments/MomoPaymentService.php`
- Create: `app/Services/Payments/MomoPaymentException.php`
- Create: `tests/Unit/MomoPaymentServiceTest.php`
- Modify: `config/services.php`
- Modify: `.env.example`

**Interfaces:**
- Produces: `isConfigured(): bool`.
- Produces: `createPayment(PaymentTransaction $attempt, string $redirectUrl, string $ipnUrl): array`.
- Produces: `verifyPaymentCallback(array $payload): bool`.
- Produces: `refund(PaymentTransaction $refund, string $originalTransId, string $description): array`.
- Produces: `queryRefund(PaymentTransaction $refund): array`.

- [ ] **Step 1: Write failing service tests with literal expected signatures**

Configure `partnerCode=MOMO_TEST`, `accessKey=test-access`, `secretKey=test-secret`, merchant order ID `PAY-1`, request ID `REQ-1`, amount `350000`, order info `Thanh toan don hang #1`, and the two callback URLs below. Fake the MoMo response and assert the exact outgoing payload.

```php
Http::fake(['https://test-payment.momo.vn/v2/gateway/api/create' => Http::response([
    'resultCode' => 0,
    'payUrl' => 'https://test-payment.momo.vn/pay/abc',
])]);

$response = $service->createPayment($attempt, 'https://shop.test/momo/result', 'https://shop.test/momo/ipn');

Http::assertSent(fn (Request $request): bool =>
    $request['requestType'] === 'captureWallet'
    && $request['amount'] === 350000
    && $request['signature'] === 'e736562537e1b03645bfaa4f9170a309d186b03ba904f11627b525fced419e7d'
    && ! array_key_exists('secretKey', $request->data())
);
$this->assertSame('https://test-payment.momo.vn/pay/abc', $response['payUrl']);
```

Add separate tests for configuration readiness, valid/invalid callback signatures, refund payload, refund query payload, timeout conversion, and a response missing `payUrl`.

- [ ] **Step 2: Run service tests and verify RED**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Unit\MomoPaymentServiceTest.php
```

Expected: failure because the service classes do not exist.

- [ ] **Step 3: Add environment-backed MoMo configuration**

```php
'momo' => [
    'enabled' => env('MOMO_ENABLED', false),
    'base_url' => env('MOMO_BASE_URL', 'https://test-payment.momo.vn'),
    'partner_code' => env('MOMO_PARTNER_CODE'),
    'access_key' => env('MOMO_ACCESS_KEY'),
    'secret_key' => env('MOMO_SECRET_KEY'),
    'partner_name' => env('MOMO_PARTNER_NAME'),
    'store_id' => env('MOMO_STORE_ID'),
    'payment_timeout' => env('MOMO_PAYMENT_TIMEOUT', 15),
    'refund_timeout' => env('MOMO_REFUND_TIMEOUT', 30),
],
```

Add matching empty credential entries to `.env.example` with `MOMO_ENABLED=false`.

- [ ] **Step 4: Implement canonical signing and HTTP methods**

Build canonical strings from explicit ordered key lists for each MoMo operation. Use `hash_hmac('sha256', $raw, $secret)` and `hash_equals` for verification. Use `Http::asJson()->acceptJson()->timeout($timeoutSeconds)`; throw `MomoPaymentException` with a normalized Vietnamese message on transport, malformed, or provider errors.

- [ ] **Step 5: Run service tests**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Unit\MomoPaymentServiceTest.php
```

Expected: pass with no real network calls.

- [ ] **Step 6: Commit service and configuration**

```powershell
git add .env.example config/services.php app/Services/Payments/MomoPaymentService.php app/Services/Payments/MomoPaymentException.php tests/Unit/MomoPaymentServiceTest.php
git commit -m "feat: add MoMo gateway service"
```

---

### Task 4: Create and Retry MoMo Payments

**Files:**
- Create: `app/Services/Payments/MomoPaymentManager.php`
- Create: `app/Http/Controllers/MomoPaymentController.php`
- Create: `tests/Feature/MomoCheckoutTest.php`
- Modify: `app/Http/Controllers/StorefrontController.php`
- Modify: `resources/views/shop/checkout-ghn.blade.php`
- Modify: `resources/views/orders/index.blade.php`
- Modify: `resources/views/orders/show.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: Task 2 transaction model and Task 3 gateway service.
- Produces: `MomoPaymentManager::start(Order $order): string`, returning a provider `payUrl`.
- Produces route `payments.momo.start` for authenticated owners.

- [ ] **Step 1: Write failing checkout availability, creation, and retry tests**

Cover disabled UI, enabled UI, order creation with `payment_method=momo`, external redirect, API failure preserving the unpaid order, retry ownership, and a distinct transaction per retry.

```php
public function test_retry_creates_a_new_attempt_and_redirects_to_momo(): void
{
    $user = User::factory()->create();
    $order = $this->makeMomoOrder($user, 'unpaid');
    $order->paymentTransactions()->create($this->failedAttempt($order));
    Http::fake(['*/v2/gateway/api/create' => Http::response([
        'resultCode' => 0,
        'payUrl' => 'https://test-payment.momo.vn/pay/retry-2',
    ])]);

    $this->actingAs($user)
        ->post(route('payments.momo.start', $order))
        ->assertRedirect('https://test-payment.momo.vn/pay/retry-2');

    $this->assertSame(2, $order->paymentTransactions()->where('kind', 'payment')->count());
}
```

- [ ] **Step 2: Run checkout tests and verify RED**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\MomoCheckoutTest.php
```

Expected: failure because MoMo is not a valid checkout method and retry routes do not exist.

- [ ] **Step 3: Implement payment orchestration**

Generate merchant IDs using an application prefix, order ID, timestamp, and random suffix restricted to MoMo's allowed characters. Persist the attempt before calling MoMo. Mark it `pending` only after a valid `payUrl`; mark it `failed` on a final create error.

```php
public function start(Order $order): string
{
    throw_unless($this->gateway->isConfigured(), MomoPaymentException::class, 'MoMo chưa được cấu hình.');
    $attempt = $order->paymentTransactions()->create($this->newPaymentAttributes($order));
    $response = $this->gateway->createPayment($attempt, route('payments.momo.result'), route('payments.momo.ipn'));
    $attempt->update(['status' => 'pending', 'response_payload' => $response]);

    return $response['payUrl'];
}
```

- [ ] **Step 4: Extend checkout without changing cash/bank behavior**

Allow `payment_method` values `cash,bank_transfer,momo`, pass `$momoEnabled` to the checkout view, return the newly created `Order` from the database transaction, and call `MomoPaymentManager::start` only after commit. On gateway failure, redirect to `user.orders.show` with an error and the retry action.

- [ ] **Step 5: Add customer retry UI and authorization**

Render `Thanh toán lại với MoMo` only for the authenticated owner when method is `momo`, payment is `unpaid`, and order status is neither completed, cancelled, nor cancellation-pending. Enforce the same rules in the controller before calling the manager.

- [ ] **Step 6: Run checkout and cart/order regressions**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\MomoCheckoutTest.php tests\Feature\CartVariantReplacementTest.php tests\Feature\OrderPurchasePageTest.php
```

Expected: pass.

- [ ] **Step 7: Commit checkout and retry flow**

```powershell
git add app/Services/Payments/MomoPaymentManager.php app/Http/Controllers/MomoPaymentController.php app/Http/Controllers/StorefrontController.php resources/views/shop/checkout-ghn.blade.php resources/views/orders/index.blade.php resources/views/orders/show.blade.php routes/web.php tests/Feature/MomoCheckoutTest.php
git commit -m "feat: add MoMo checkout and payment retry"
```

---

### Task 5: Verified IPN and Browser Result Page

**Files:**
- Create: `resources/views/payments/momo-result.blade.php`
- Create: `tests/Feature/MomoPaymentCallbackTest.php`
- Modify: `app/Http/Controllers/MomoPaymentController.php`
- Modify: `app/Services/Payments/MomoPaymentManager.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: `MomoPaymentManager::handleIpn(array $payload): void`.
- Produces public POST route `payments.momo.ipn` and GET route `payments.momo.result`.

- [ ] **Step 1: Write failing callback security and idempotency tests**

Use literal, correctly signed fixtures for success and separate fixtures for bad signature, amount mismatch, unknown merchant order, partner mismatch, and duplicate success.

```php
public function test_valid_ipn_marks_matching_order_paid_once(): void
{
    $attempt = $this->pendingAttempt(amount: 350000);
    $payload = $this->signedSuccessPayload($attempt, transId: '311445566');

    $this->postJson(route('payments.momo.ipn'), $payload)->assertOk();
    $this->postJson(route('payments.momo.ipn'), $payload)->assertOk();

    $this->assertSame('paid', $attempt->order->fresh()->payment_status);
    $this->assertNotNull($attempt->order->fresh()->paid_at);
    $this->assertSame('succeeded', $attempt->fresh()->status);
    $this->assertSame(1, $attempt->order->paymentTransactions()->where('status', 'succeeded')->count());
}
```

Also assert that visiting the result URL with forged `resultCode=0` leaves the order unpaid.

- [ ] **Step 2: Run callback tests and verify RED**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\MomoPaymentCallbackTest.php
```

Expected: failure because IPN and result routes do not exist.

- [ ] **Step 3: Add the narrowly scoped CSRF exception**

```php
$middleware->validateCsrfTokens(except: [
    'thanh-toan/momo/ipn',
]);
```

Do not exclude any other payment or order route.

- [ ] **Step 4: Implement verified and locked IPN handling**

Verify the signature before database access that changes state. Inside a transaction, lock the attempt and order, compare all persisted identity and amount fields, then update only a `resultCode=0` transaction. If already succeeded with the same provider transaction ID, return success without another state change.

- [ ] **Step 5: Implement a read-only browser result page**

Resolve the order through a known attempt, authorize the signed-in owner when order details are shown, and derive display text from persisted transaction/order state. Query parameters may select copy but may not write models.

- [ ] **Step 6: Run callback and checkout tests**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\MomoPaymentCallbackTest.php tests\Feature\MomoCheckoutTest.php
```

Expected: pass.

- [ ] **Step 7: Commit callback handling**

```powershell
git add bootstrap/app.php routes/web.php app/Http/Controllers/MomoPaymentController.php app/Services/Payments/MomoPaymentManager.php resources/views/payments/momo-result.blade.php tests/Feature/MomoPaymentCallbackTest.php
git commit -m "feat: verify MoMo payment notifications"
```

---

### Task 6: Shared Cancellation and Automatic Full Refund

**Files:**
- Create: `app/Services/Orders/OrderCancellationService.php`
- Create: `app/Jobs/ProcessMomoRefund.php`
- Create: `tests/Feature/MomoRefundTest.php`
- Modify: `app/Http/Controllers/OrderController.php`
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `app/Models/Order.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: successful MoMo payment and refund methods from Tasks 2 and 3.
- Produces: `OrderCancellationService::cancel(Order $order, string $reason): void`.
- Produces: queued `ProcessMomoRefund` accepting a refund transaction ID.

- [ ] **Step 1: Write failing cancellation/refund tests**

Cover unpaid MoMo cancellation without API, paid MoMo cancellation entering `cancellation_pending`, successful full refund, duplicate job execution, timeout/processing state, final failure, and admin retry.

```php
public function test_successful_refund_finalizes_cancellation_and_restores_stock_once(): void
{
    Queue::fake();
    $order = $this->paidMomoOrder(stock: 8, quantity: 2, total: 350000);
    $this->cancellation->cancel($order, 'Khách không còn nhu cầu');
    $refund = $order->paymentTransactions()->where('kind', 'refund')->firstOrFail();

    Http::fake(['*/v2/gateway/api/refund' => Http::response([
        'resultCode' => 0,
        'message' => 'Successful.',
        'transId' => 911223344,
    ])]);
    (new ProcessMomoRefund($refund->id))->handle($this->gateway);
    (new ProcessMomoRefund($refund->id))->handle($this->gateway);

    $this->assertSame('cancelled', $order->fresh()->status);
    $this->assertSame('refunded', $order->fresh()->payment_status);
    $this->assertSame(10, $order->items->first()->variant->fresh()->stock);
}
```

- [ ] **Step 2: Run refund tests and verify RED**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\MomoRefundTest.php
```

Expected: failure because cancellation service, refund records, and job do not exist.

- [ ] **Step 3: Centralize cancellation transitions**

Move stock restoration out of both controllers. For non-paid-MoMo orders, lock, cancel, and restore once. For paid MoMo, lock, change to `cancellation_pending`/`refund_pending`, create one refund linked to the successful payment, and dispatch the job with `DB::afterCommit`.

- [ ] **Step 4: Implement the refund job**

Use a 30-second gateway timeout. Lock and short-circuit completed refunds. Send the full persisted order amount and original successful `transId`. On result `0`, lock the order and refund, set `refunded`/`cancelled`, set `refunded_at`, and restore stock exactly once. Keep provider-processing results pending; convert final provider rejections to `refund_failed`.

```php
public int $tries = 4;

public function backoff(): array
{
    return [30, 120, 300];
}
```

- [ ] **Step 5: Add admin-only failed-refund retry**

Reject retries unless method is MoMo, payment status is `refund_failed`, order status is `cancellation_pending`, and a successful original payment exists. Reuse the same refund attempt/request identity when querying or retrying an uncertain transaction; create no duplicate refund amount.

- [ ] **Step 6: Run cancellation and refund regression tests**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\MomoRefundTest.php tests\Feature\UserOrderCancellationTest.php tests\Feature\AdminOrderCancellationTest.php
```

Expected: pass and stock changes exactly once in every cancellation path.

- [ ] **Step 7: Commit cancellation and refund behavior**

```powershell
git add app/Services/Orders/OrderCancellationService.php app/Jobs/ProcessMomoRefund.php app/Http/Controllers/OrderController.php app/Http/Controllers/AdminOrderController.php app/Models/Order.php routes/web.php tests/Feature/MomoRefundTest.php
git commit -m "feat: refund cancelled MoMo orders"
```

---

### Task 7: Admin Payment Controls, Refund UI, Reports, and Retention

**Files:**
- Create: `tests/Feature/AdminMomoPaymentStateTest.php`
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `app/Models/Order.php`
- Modify: `resources/views/admin/orders/index.blade.php`
- Modify: `resources/views/orders/index.blade.php`
- Modify: `resources/views/orders/show.blade.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `routes/console.php`
- Modify: `tests/Feature/RevenueReportTest.php`
- Modify: `tests/Feature/CompletedOrderRetentionTest.php`

**Interfaces:**
- Consumes: order/payment/refund states from Tasks 2 and 6.
- Produces: visible MoMo, refund-pending, refunded, and refund-failed labels; server-side rejection of manual MoMo payment changes.

- [ ] **Step 1: Write failing policy and presentation tests**

```php
public function test_admin_cannot_manually_change_momo_payment_status(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $order = $this->makeOrder(['payment_method' => 'momo', 'payment_status' => 'unpaid']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.payment', $order), ['payment_status' => 'paid'])
        ->assertStatus(422);

    $this->assertSame('unpaid', $order->fresh()->payment_status);
}
```

Add tests that admin cards show `MoMo`, hide the manual payment button for MoMo, show refund retry only on `refund_failed`, group `cancellation_pending` into the cancelled tab, and prevent deletion while refunds are unresolved.

- [ ] **Step 2: Run admin payment-state tests and verify RED**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\AdminMomoPaymentStateTest.php
```

Expected: failure because MoMo can still be changed manually and refund labels do not exist.

- [ ] **Step 3: Enforce server-side transition rules**

Abort manual MoMo payment changes with status 422. Update `canBeDeletedByAdmin`, deletion scopes, active sorting, and visible-history scopes so `cancellation_pending` is visible and undeletable. Map the cancelled admin tab to both `cancellation_pending` and `cancelled`.

- [ ] **Step 4: Render payment/refund labels and actions**

Show payment method labels from `Order::payment_label`, exact payment-state badges, and retry forms only when allowed. Customer views show `Đang hoàn tiền`, `Đã hoàn tiền`, or `Hoàn tiền thất bại` without claiming success early.

- [ ] **Step 5: Update reports and archive behavior**

Revenue remains limited to `payment_status=paid` and excludes both `cancellation_pending` and `cancelled`. The archive command must not archive unresolved refunds and may archive refunded cancellations under existing retention rules.

- [ ] **Step 6: Run all order, report, and retention tests**

```powershell
php vendor\bin\phpunit --do-not-cache-result tests\Feature\AdminMomoPaymentStateTest.php tests\Feature\RevenueReportTest.php tests\Feature\CompletedOrderRetentionTest.php tests\Feature\OrderPurchasePageTest.php
```

Expected: pass.

- [ ] **Step 7: Commit state presentation and reporting**

```powershell
git add app/Http/Controllers/AdminOrderController.php app/Models/Order.php resources/views/admin/orders/index.blade.php resources/views/orders/index.blade.php resources/views/orders/show.blade.php app/Http/Controllers/ReportController.php routes/console.php tests/Feature/AdminMomoPaymentStateTest.php tests/Feature/RevenueReportTest.php tests/Feature/CompletedOrderRetentionTest.php
git commit -m "feat: show and enforce MoMo payment states"
```

---

### Task 8: Operations Documentation and Final Verification

**Files:**
- Modify: `README.md`
- Test: complete project suite

**Interfaces:**
- Consumes: all preceding tasks.
- Produces: exact sandbox/production configuration and queue-worker instructions for deployment.

- [ ] **Step 1: Document MoMo configuration and local callback requirements**

Add the environment variable names, keep credentials blank, explain `MOMO_ENABLED=false` deployment-first rollout, document public HTTPS IPN requirements, and include these commands:

```powershell
php artisan migrate
php artisan queue:work --tries=4
php artisan schedule:work
```

Explain that sandbox uses `https://test-payment.momo.vn`, production uses `https://payment.momo.vn`, and credentials differ by environment.

- [ ] **Step 2: Run syntax checks on every changed PHP file**

```powershell
Get-ChildItem app,database\migrations,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Expected: every file reports `No syntax errors detected`.

- [ ] **Step 3: Run the complete test suite**

```powershell
php vendor\bin\phpunit --do-not-cache-result
```

Expected: 0 failures and 0 errors.

- [ ] **Step 4: Verify formatting and migration status**

```powershell
git diff --check
php artisan migrate:status
```

Expected: no whitespace errors; both new migrations appear in the migration list.

- [ ] **Step 5: Review the diff against the approved spec**

Confirm each spec heading has corresponding code/tests: admin tabs, checkout availability, attempt persistence, retry, IPN, result page, cancellation, refund, manual-control restriction, reporting, retention, and operations documentation.

- [ ] **Step 6: Commit operations documentation**

```powershell
git add README.md
git commit -m "docs: document MoMo payment operations"
```

- [ ] **Step 7: Record sandbox acceptance checks**

After credentials and a public HTTPS callback are available, execute one successful payment, one abandoned payment and retry, one duplicate-IPN replay, and one full refund. Record the corresponding internal order ID, payment transaction ID, and final state without recording credentials or signature strings.
