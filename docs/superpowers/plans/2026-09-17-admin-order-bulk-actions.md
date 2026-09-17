# Admin Order Bulk Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add multi-order printing, XLSX export, local GHN provider marking for testing, safe archiving, and full-row navigation to the admin order list.

**Architecture:** Keep the existing `AdminOrderController` as the HTTP boundary and add a focused `AdminOrderBulkActionService` for loading selected orders, preparing output data, marking the local GHN provider field, and archiving eligible orders. The current `Order` retention rules and `SimpleXlsx` utility remain the integration points; the Blade table submits one protected bulk form and uses a dedicated print view.

**Tech Stack:** Laravel, PHP, Blade, Eloquent, Laravel HTTP client, `SimpleXlsx`, PHPUnit feature tests, Vite.

**Spec:** `docs/superpowers/specs/2026-09-17-admin-order-bulk-actions-design.md`

## Global Constraints

- Selection applies only to orders rendered on the current paginated page; cross-page selection is out of scope.
- “Gán nhiều đơn cho GHN” only sets `orders.shipping_provider` to `ghn`; it does not call GHN or create a real tracking code.
- Printing uses the browser print dialog and creates one A4 packing slip per order; no PDF dependency is added.
- Archiving sets `archived_at` and never deletes order rows.
- Existing admin cancellation/detail behavior must remain unchanged.
- Existing unrelated working-tree changes must not be overwritten or staged.
- Each production behavior must have a failing feature/unit test before its implementation.

---

### Task 1: Add bulk-action service contracts and selection validation tests

**Files:**
- Create: `app/Services/Orders/AdminOrderBulkActionService.php`
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminOrderBulkActionsTest.php`

**Interfaces:**
- Consumes: selected `order_ids` from the admin bulk form and the existing `Order` and `SimpleXlsx` contracts.
- Produces: controller methods `bulkPrint`, `bulkExport`, `bulkGhn`, and `bulkArchive`; named routes `admin.orders.bulk.print`, `admin.orders.bulk.export`, `admin.orders.bulk.ghn`, and `admin.orders.bulk.archive`; service methods `loadSelected`, `printData`, `exportRows`, `assignToGhn`, and `archive`.

- [ ] **Step 1: Write failing tests for route existence, admin authorization, and empty selection validation**

Add tests with this behavior:

```php
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
```

- [ ] **Step 2: Run the focused test and verify it fails for missing bulk routes**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=bulk_routes_require`

Expected: FAIL because the four bulk route names and controller methods do not exist yet.

- [ ] **Step 3: Add the four POST routes inside the existing admin middleware group**

Add this route block beside the existing admin order routes:

```php
Route::post('/orders/bulk/print', [AdminOrderController::class, 'bulkPrint'])->name('admin.orders.bulk.print');
Route::post('/orders/bulk/export', [AdminOrderController::class, 'bulkExport'])->name('admin.orders.bulk.export');
Route::post('/orders/bulk/ghn', [AdminOrderController::class, 'bulkGhn'])->name('admin.orders.bulk.ghn');
Route::post('/orders/bulk/archive', [AdminOrderController::class, 'bulkArchive'])->name('admin.orders.bulk.archive');
```

- [ ] **Step 4: Add a shared selected-ID validator in `AdminOrderController`**

Implement a private method that validates `order_ids` as a required non-empty array of existing integer IDs and returns the normalized unique IDs:

```php
private function selectedOrderIds(Request $request): array
{
    $data = $request->validate([
        'order_ids' => ['required', 'array', 'min:1'],
        'order_ids.*' => ['required', 'integer', 'distinct', 'exists:orders,id'],
    ]);

    return array_values(array_unique(array_map('intval', $data['order_ids'])));
}
```

- [ ] **Step 5: Implement `AdminOrderBulkActionService::loadSelected`**

Load only non-archived orders whose IDs are selected, eager-load `user`, `items`, and `items.variant.product`, and abort with a validation exception if the count does not match the submitted ID count. Preserve submitted order order by sorting the collection by the selected ID sequence.

- [ ] **Step 6: Run the focused validation test and commit the isolated route/service foundation**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=bulk_routes_require`

Expected: PASS.

Commit only the new plan-independent implementation files for this task:

```bash
git add app/Http/Controllers/AdminOrderController.php app/Services/Orders/AdminOrderBulkActionService.php routes/web.php tests/Feature/AdminOrderBulkActionsTest.php
git commit -m "feat: add admin order bulk action endpoints"
```

If Git remains read-only, leave the files unstaged and report that limitation.

### Task 2: Make the order table selectable and every row navigable

**Files:**
- Modify: `resources/views/admin/orders/index.blade.php`
- Modify: `tests/Feature/AdminOrderIndexTest.php`

**Interfaces:**
- Consumes: the four named bulk routes from Task 1.
- Produces: a form with `order_ids[]` checkboxes, select-all behavior, selected count, disabled-until-selected buttons, and `data-order-url` row navigation.

- [ ] **Step 1: Extend the index feature test with the required bulk controls**

Assert the admin index contains the route URLs, a `bulk-order-form`, the selection counter, and a row URL attribute, while still asserting no `Thao tác` column exists.

- [ ] **Step 2: Run the new index assertions and verify they fail**

Run: `php artisan test tests/Feature/AdminOrderIndexTest.php`

Expected: FAIL because the current table has no bulk form/actions and no row navigation attribute.

- [ ] **Step 3: Wrap the table in a CSRF-protected POST bulk form**

Give the form `id="bulk-order-form"` and keep each visible row checkbox named `order_ids[]`. Set the form's default action to the print endpoint and use `formaction` on each action button for the other endpoints.

- [ ] **Step 4: Add the four bulk buttons and selection counter**

Render `In các đơn đã chọn`, `Xuất Excel`, `Gán GHN`, and `Lưu trữ` buttons with `disabled` initially. Use `formtarget="_blank"` only for the print button. Add confirmation messages for GHN and archive actions.

- [ ] **Step 5: Add row navigation JavaScript and accessible keyboard behavior**

Render each body row with `data-order-url="{{ route('admin.orders.show', $order) }}"`, `tabindex="0"`, and a class indicating it is clickable. Add delegated click and keydown handlers that ignore interactive descendants (`input`, `a`, `button`, `select`, `textarea`, `label`) and navigate the row otherwise. Keep the header checkbox limited to visible rows.

- [ ] **Step 6: Remove the old current-page CSV exporter and stale action-column rules**

Delete the `data-export-orders` button and its client-side CSV function. Remove CSS selectors that only styled the deleted action column, while retaining table styles used by the current columns.

- [ ] **Step 7: Run the index and detail tests**

Run: `php artisan test tests/Feature/AdminOrderIndexTest.php tests/Feature/AdminOrderDetailTest.php`

Expected: PASS.

### Task 3: Implement A4 print slips

**Files:**
- Create: `resources/views/admin/orders/print.blade.php`
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `app/Services/Orders/AdminOrderBulkActionService.php`
- Modify: `tests/Feature/AdminOrderBulkActionsTest.php`

**Interfaces:**
- Consumes: `AdminOrderBulkActionService::loadSelected(array $ids): Collection`.
- Produces: `AdminOrderController::bulkPrint(Request $request): View` and the `admin.orders.print` Blade view.

- [ ] **Step 1: Add the failing print feature test**

Create two selected orders with items and assert the response includes both order numbers, both recipient names, `@media print`, and `page-break-after`. Submit one non-selected order ID and assert it is absent.

- [ ] **Step 2: Run the print test and verify it fails**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=print`

Expected: FAIL because `bulkPrint` and the print view do not exist.

- [ ] **Step 3: Implement `bulkPrint`**

Validate selected IDs, call `loadSelected`, and return `view('admin.orders.print', ['orders' => $orders])`.

- [ ] **Step 4: Build the print view with one A4 slip per order**

Include order number/date, customer and shipping address, item rows with variants/quantities/prices, subtotal, shipping fee, total, payment status, and GHN code. Use a print stylesheet with `@page { size: A4; margin: 12mm; }`, `.packing-slip { page-break-after: always; }`, and `.packing-slip:last-child { page-break-after: auto; }`. Trigger `window.print()` after load and keep a non-print back link.

- [ ] **Step 5: Run the print test and the focused order tests**

Run:

```powershell
php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=print
php artisan test tests/Feature/AdminOrderIndexTest.php
```

Expected: PASS.

### Task 4: Implement selected-order XLSX export

**Files:**
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `app/Services/Orders/AdminOrderBulkActionService.php`
- Modify: `tests/Feature/AdminOrderBulkActionsTest.php`

**Interfaces:**
- Consumes: selected orders with user/items from `loadSelected` and `SimpleXlsx::create(array $rows): string`.
- Produces: `AdminOrderController::bulkExport(Request $request): BinaryFileResponse` with an XLSX download.

- [ ] **Step 1: Add the failing XLSX response test**

Submit two selected IDs and assert status 200, the XLSX content type, a filename containing `don-hang-da-chon`, and response headers. Verify the generated workbook contains the selected order IDs by reading the downloaded ZIP/XML in the test or by asserting the generated file path exists before cleanup.

- [ ] **Step 2: Run the export test and verify it fails**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=export`

Expected: FAIL because the export endpoint has no implementation.

- [ ] **Step 3: Implement `exportRows` with one row per order**

Use headers exactly as follows:

```php
['Mã đơn', 'Ngày tạo', 'Trạng thái', 'Người nhận', 'Số điện thoại', 'Địa chỉ', 'Sản phẩm', 'Số lượng', 'Tạm tính', 'Phí vận chuyển', 'Tổng tiền', 'Thanh toán', 'Mã GHN', 'Đơn vị vận chuyển']
```

For each order, combine item product names and variants into one product summary, sum quantities and item prices, and use `GHN` when `shipping_provider` is `ghn` (or a legacy `ghn_order_code` is present); otherwise use `—`.

- [ ] **Step 4: Implement `bulkExport`**

Create the XLSX via `SimpleXlsx::create($rows)`, return `response()->download()` with filename `don-hang-da-chon-`.date('Y-m-d-H-i').'.xlsx'`, set the official XLSX content type, and call `deleteFileAfterSend(true)`.

- [ ] **Step 5: Run the export test and focused order suite**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php tests/Feature/AdminOrderIndexTest.php`

Expected: PASS.

### Task 5: Implement local GHN provider assignment

**Files:**
- Modify: `app/Services/Orders/AdminOrderBulkActionService.php`
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `tests/Feature/AdminOrderBulkActionsTest.php`

**Interfaces:**
- Consumes: selected orders and the existing order status rules.
- Produces: `AdminOrderBulkActionService::assignToGhn(Collection $orders): array` returning `['success' => int, 'failed' => int, 'skipped' => int, 'messages' => array]`; `AdminOrderController::bulkGhn(Request $request): RedirectResponse`.

- [ ] **Step 1: Add the failing local-assignment test**

Fake HTTP requests, create active, cancelled, and completed selected orders, submit the bulk endpoint, and assert active orders get `shipping_provider = 'ghn'`, no `ghn_order_code` is generated, terminal orders are skipped, and no HTTP request is sent.

- [ ] **Step 2: Run the local-assignment tests and verify the new tests fail**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=ghn`

Expected: FAIL because the provider field and local assignment behavior do not exist.

- [ ] **Step 3: Implement independent eligibility and persistence**

Skip cancelled/completed and already assigned orders. For each remaining order, update only `shipping_provider` to `ghn`; never call `GHNService` and never write `ghn_order_code`. Legacy orders with an existing code may be synchronized to the provider field without an external request.

- [ ] **Step 4: Implement `bulkGhn` feedback**

Redirect to the index with a status message containing success/skipped/failed counts and append the first few per-order failure messages. Preserve the current query string when possible.

- [ ] **Step 5: Run local-assignment and bulk tests**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=ghn`

Expected: PASS, with no HTTP request made by the bulk assignment test.

### Task 6: Implement safe bulk archiving

**Files:**
- Modify: `app/Services/Orders/AdminOrderBulkActionService.php`
- Modify: `app/Http/Controllers/AdminOrderController.php`
- Modify: `tests/Feature/AdminOrderBulkActionsTest.php`

**Interfaces:**
- Consumes: selected non-archived orders and `Order::canBeDeletedByAdmin()` eligibility.
- Produces: `AdminOrderBulkActionService::archive(Collection $orders): array` returning `['archived' => int, 'skipped' => int, 'messages' => array]`; `AdminOrderController::bulkArchive(Request $request): RedirectResponse`.

- [ ] **Step 1: Add the failing archive eligibility test**

Create one cancelled order, one completed order older than seven days, one recent completed order, and one pending order. Submit all IDs and assert only the first two receive `archived_at`, while recent/pending orders remain unarchived and the response reports skipped IDs.

- [ ] **Step 2: Run the archive test and verify it fails**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php --filter=archive`

Expected: FAIL because `archive` and `bulkArchive` do not exist.

- [ ] **Step 3: Implement archive with row locks and the existing model rule**

Inside a database transaction, lock each selected order, re-check `canBeDeletedByAdmin()`, set `archived_at` only for eligible rows, and collect skipped order labels. Re-check inside the transaction so concurrent status/refund changes cannot bypass retention rules.

- [ ] **Step 4: Implement redirect feedback**

Return to the index with `Đã lưu trữ X đơn hàng` and include skipped order labels when present. Do not call the existing `destroyAll()` method and do not hard-delete rows.

- [ ] **Step 5: Run archive tests and existing retention tests**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php tests/Feature/CompletedOrderRetentionTest.php`

Expected: PASS.

### Task 7: Full verification and cleanup

**Files:**
- Modify: only files that fail the verification checks above.

- [ ] **Step 1: Run the complete focused feature set**

Run: `php artisan test tests/Feature/AdminOrderBulkActionsTest.php tests/Feature/AdminOrderIndexTest.php tests/Feature/AdminOrderDetailTest.php tests/Feature/AdminOrderCancellationTest.php tests/Feature/CompletedOrderRetentionTest.php tests/Feature/GHNIntegrationTest.php`

Expected: all tests for the changed order/GHN flows pass. If an unrelated pre-existing failure appears, record its test name and do not alter unrelated behavior.

- [ ] **Step 2: Check PHP syntax for all changed PHP files**

Run:

```powershell
Get-ChildItem app,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Expected: every changed PHP file reports `No syntax errors detected`.

- [ ] **Step 3: Build frontend assets**

Run: `npm run build`

Expected: Vite completes successfully.

- [ ] **Step 4: Check whitespace and review the diff**

Run: `git diff --check` and `git diff --stat`.

Expected: no whitespace errors; the diff contains only the bulk order feature, its tests, and the approved design/plan documents.

- [ ] **Step 5: Run the final route list check**

Run: `php artisan route:list --name=admin.orders.bulk`

Expected: all four bulk route names are listed under the admin middleware.

- [ ] **Step 6: Commit the completed feature if repository permissions allow it**

```bash
git add app/Http/Controllers/AdminOrderController.php app/Services/Orders/AdminOrderBulkActionService.php resources/views/admin/orders/index.blade.php resources/views/admin/orders/print.blade.php routes/web.php tests/Feature/AdminOrderBulkActionsTest.php tests/Feature/AdminOrderIndexTest.php tests/Feature/GHNIntegrationTest.php
git commit -m "feat: add admin order bulk operations"
```

Do not stage unrelated existing user changes. If `.git/index.lock` remains unavailable, report that the implementation is complete but the commit could not be created.
