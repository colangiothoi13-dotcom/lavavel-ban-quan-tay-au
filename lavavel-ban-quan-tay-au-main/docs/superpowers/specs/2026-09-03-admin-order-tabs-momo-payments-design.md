# Admin Order Tabs, MoMo Payments, and Refunds Design

## Summary

Replace the admin order-status dropdown with status tabs while retaining payment filtering. Add a direct MoMo Payment Gateway V2 integration for one-time wallet payments, server-verified payment notifications, customer retries, and automatic full refunds when a paid MoMo order is cancelled.

The integration remains unavailable until valid MoMo credentials are configured. Cash and bank-transfer checkout continue to work as they do today.

## Goals

- Make active and historical orders easier for administrators to track by status.
- Let customers select MoMo during checkout and continue to MoMo's hosted payment page.
- Update an order to paid only from a cryptographically verified MoMo result.
- Preserve an unpaid MoMo order after an abandoned or failed payment so the customer can retry.
- Automatically return the full paid amount to the originating MoMo account when a paid MoMo order is cancelled.
- Make payment and refund processing idempotent, auditable, and safe against forged or repeated callbacks.

## Non-goals

- Partial refunds or refunds for individual order items.
- Storing or linking a customer's MoMo wallet in this application.
- Recurring/tokenized payments, MoMo Pay Later, or direct card collection.
- Automatic refunds for cash or bank-transfer orders.
- Replacing the existing GHN shipping integration.

## Admin Order Navigation

The status `<select>` is replaced with these tabs:

- `Tất cả`
- `Chờ xác nhận`
- `Đã xác nhận`
- `Đang giao`
- `Hoàn thành`
- `Đã hủy`

Each tab displays its order count. The active tab is visually distinct and its URL carries the existing `status` query parameter. The payment filter remains a `<select>` and is preserved when switching tabs. The `Đã hủy` tab includes both orders whose refund is still being processed and fully cancelled orders; each card displays its exact status.

Payment filtering supports:

- `Tất cả thanh toán`
- `Đã thanh toán`
- `Chưa thanh toán`
- `Đang hoàn tiền`
- `Đã hoàn tiền`
- `Hoàn tiền thất bại`

The existing product images on admin order cards remain unchanged.

## Checkout Experience

The checkout page adds a `Ví MoMo` payment option beside cash and bank transfer.

MoMo is selectable only when all required configuration values are present and the integration is enabled. Otherwise, it remains visible but disabled with the text `MoMo chưa được cấu hình`.

When a customer submits a MoMo order:

1. The server validates the address, cart, stock, shipping fee, and total as it does today.
2. In a database transaction, it creates the order and order items, deducts stock, removes purchased cart items, and records an initial MoMo payment attempt.
3. After the database transaction commits, it calls MoMo's `/v2/gateway/api/create` endpoint with `requestType=captureWallet`.
4. If MoMo returns a valid `payUrl`, the customer is redirected there.
5. If the API is unavailable or returns an invalid response, the order remains unpaid and the customer is sent to the order page with a retry message.

Cash and bank-transfer orders follow the existing post-checkout behavior.

## Payment Attempts and Retry

An unpaid MoMo order remains an order rather than being discarded. The customer's order list and order-detail page show a `Thanh toán lại với MoMo` action while the order is still eligible for payment.

Each retry creates a new payment transaction with a new merchant order ID and request ID. Old attempts are retained for audit history. Payment is prohibited for completed, cancelled, or cancellation-pending orders and for orders belonging to another user.

## Payment Result Processing

MoMo sends server-to-server results to a public IPN endpoint. The IPN endpoint does not use session authentication or CSRF protection; its authenticity comes from strict signature verification.

For every IPN, the application:

1. Reconstructs the canonical MoMo signature string from the documented fields.
2. Calculates HMAC-SHA256 with the configured secret and compares it with `hash_equals`.
3. Finds the exact payment transaction using the merchant order ID and request ID.
4. Confirms that the partner code, application order, and amount match persisted values.
5. Locks the transaction and order rows before making a state change.
6. Marks the order paid only when `resultCode` is `0` and records MoMo's `transId`.

Repeated IPNs produce the same final state and never apply payment twice. An invalid signature, unknown attempt, partner mismatch, or amount mismatch changes no data and receives an error response.

The browser redirect is used only to display one of these outcomes:

- payment successful;
- confirmation still pending;
- payment cancelled;
- payment failed.

The redirect never marks an order paid because browser query parameters are not authoritative.

## Cancellation and Automatic Refunds

Cancellation behavior depends on payment method and payment state:

### Unpaid MoMo, cash, and bank-transfer orders

The existing cancellation behavior remains: the order becomes `cancelled` and stock is returned once. No MoMo refund request is created.

### Paid MoMo orders

When either the customer or an administrator cancels a paid MoMo order:

1. A database transaction locks the order, verifies it can be cancelled, records the cancellation reason, changes the order to `cancellation_pending`, changes payment state to `refund_pending`, and creates one full-refund transaction.
2. A queued refund job is dispatched only after the database transaction commits.
3. The job signs and sends `/v2/gateway/api/refund` using the original successful MoMo `transId`, the full order total, and unique refund order/request IDs.
4. On a successful refund response, a database transaction changes the payment state to `refunded`, changes the order to `cancelled`, records the refund time, and returns stock exactly once.
5. A final refund failure changes the payment state to `refund_failed`; the order remains `cancellation_pending` so fulfilment cannot continue.
6. A timeout or provider-processing response remains `refund_pending`. Retries reuse the same request identity for idempotency, and the integration can query MoMo's refund-status endpoint before deciding the final state.

The admin order card shows the current refund state. A failed refund offers an administrator-only retry action. Cancellation-pending orders cannot move to fulfilment states and cannot be deleted. Cancelled paid MoMo orders cannot be deleted unless the refund is confirmed.

Only full refunds are supported in this version.

## Data Model

### `orders` additions

- `paid_at`: nullable timestamp.
- `refunded_at`: nullable timestamp.

Existing string fields are extended with these application-level values:

- `payment_method`: adds `momo`.
- `payment_status`: `unpaid`, `paid`, `refund_pending`, `refunded`, or `refund_failed`.
- `status`: adds `cancellation_pending`.

### New `payment_transactions` table

- `id`
- `order_id` foreign key
- `parent_id` nullable self-reference for a refund linked to its payment
- `provider` (`momo`)
- `kind` (`payment` or `refund`)
- `merchant_order_id`, unique
- `request_id`, unique
- `provider_transaction_id`, nullable
- `amount`
- `status` (`created`, `pending`, `processing`, `succeeded`, or `failed`)
- `result_code`, nullable
- `message`, nullable
- `response_payload`, nullable JSON containing the non-secret provider response
- timestamps

An order has many payment transactions. A refund references the successful payment transaction whose MoMo transaction ID it refunds.

Unique database constraints on merchant and request IDs provide a second layer of duplicate protection in addition to row locking.

## Components

### `MomoPaymentService`

Owns MoMo-specific protocol behavior:

- configuration readiness;
- request signature generation;
- response/IPN signature verification;
- create-payment calls;
- full-refund calls;
- refund-status queries;
- normalized errors that do not expose credentials.

It uses Laravel's HTTP client and has no responsibility for changing application models.

### `MomoPaymentController`

Owns customer-facing payment start/retry, browser redirect results, and the public IPN endpoint. It authorizes order ownership before starting or retrying payment.

### Cancellation service

Shared cancellation logic moves out of the user and admin controllers into a service so both entry points enforce identical stock and refund rules.

### `ProcessMomoRefund` job

Performs the external refund call outside the request's database transaction. It uses bounded retries/backoff for transient failures and persists each provider result. A retry keeps the original refund request ID so MoMo can apply idempotency controls.

## Routes

Authenticated customer routes:

- `POST /thanh-toan/momo/{order}` starts or retries payment.
- `GET /thanh-toan/momo/ket-qua` displays the browser-return result.

Public provider route:

- `POST /thanh-toan/momo/ipn` processes signed MoMo notifications and is excluded only from CSRF validation.

Administrator route:

- `POST /admin/orders/{order}/refund/retry` retries an eligible failed refund.

The payment-start route is rate limited. The IPN route does not trust client identity and accepts state changes only after signature and persisted-data validation.

## Configuration

`config/services.php` receives a `momo` section backed by:

- `MOMO_ENABLED=false`
- `MOMO_BASE_URL=https://test-payment.momo.vn`
- `MOMO_PARTNER_CODE=`
- `MOMO_ACCESS_KEY=`
- `MOMO_SECRET_KEY=`
- `MOMO_PARTNER_NAME=`
- `MOMO_STORE_ID=`

`.env.example` contains empty placeholders only. Secrets are never committed, stored in the database, returned to the browser, or written to logs.

Sandbox and production use the same implementation. Production activation requires replacing the base URL and credentials in the deployment environment. MoMo must be able to reach the application's public HTTPS IPN URL; local testing therefore requires a public tunnel or deployed test environment.

## Error Handling and Observability

- Network errors and malformed create-payment responses preserve the unpaid order and permit retry.
- Invalid IPNs are rejected without state changes.
- External HTTP calls occur outside database transactions.
- Provider responses are stored without secrets for audit and troubleshooting.
- Refund states distinguish pending, confirmed, and failed outcomes; the UI never claims money was returned before confirmation.
- Logs contain internal order/transaction IDs and normalized failure categories, not keys or canonical signature strings.
- The 30-second minimum refund timeout recommended by MoMo is configurable and used for refund calls.

## Reporting and Existing Behavior

- Revenue reports continue to count only orders with `payment_status=paid` and exclude cancellation-pending and cancelled orders.
- Refunded orders are not counted as revenue.
- Existing stock restoration is centralized and guarded so it runs once.
- Existing order retention and deletion rules are updated to protect unresolved refunds.
- Existing COD and bank-transfer administrator payment controls remain. Manual payment-status changes are rejected server-side for MoMo orders.

## Testing Strategy

All provider tests use Laravel HTTP fakes; the test suite never sends a real MoMo request.

Required coverage:

- admin status tabs, counts, active state, and preservation of the payment filter;
- MoMo option enabled and disabled from configuration;
- deterministic payment and refund signature generation;
- create-payment payload and redirect URL handling;
- failed create-payment call preserving the order for retry;
- retry creating a distinct attempt;
- valid successful IPN marking the order paid;
- invalid signature, amount mismatch, unknown attempt, and partner mismatch changing no state;
- duplicate successful IPNs remaining idempotent;
- browser redirect never marking an order paid;
- paid MoMo cancellation creating a full refund and entering the pending state;
- unpaid MoMo cancellation requiring no refund call;
- successful refund finalizing cancellation and returning stock once;
- repeated refund processing not returning stock twice;
- transient, processing, and final refund failure states;
- admin refund retry authorization;
- deletion and fulfilment transitions blocked during unresolved refunds;
- manual payment changes rejected for MoMo;
- COD and bank-transfer regression tests;
- complete existing test suite.

## Rollout

1. Deploy migrations and code with `MOMO_ENABLED=false`.
2. Run the complete automated suite.
3. Add sandbox credentials and a public HTTPS callback URL.
4. Start the queue worker and perform sandbox payment, cancellation, retry, duplicate-IPN, and refund tests.
5. Confirm admin filters, revenue reports, inventory restoration, and payment/refund audit records.
6. Obtain production credentials from MoMo for Business, change the base URL, and enable MoMo in production.

## Official References

- Payment initiation and IPN fields: https://developers.momo.vn/v3/docs/payment/api/payment-api/init/
- Refund and refund-query APIs: https://developers.momo.vn/v3/vi/docs/payment/api/payment-api/refund/
- Transaction query API: https://developers.momo.vn/v3/vi/docs/payment/api/payment-api/query/
- Sandbox and production onboarding: https://developers.momo.vn/v3/docs/payment/onboarding/integration-process/
