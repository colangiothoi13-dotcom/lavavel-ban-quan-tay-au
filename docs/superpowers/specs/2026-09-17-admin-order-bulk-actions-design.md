# Admin Order Bulk Actions Design

## Goal

Add reliable bulk operations to the admin order list: print selected orders as separate A4 packing slips, export selected orders to a real XLSX file, mark selected orders as using GHN for internal testing without creating remote shipments, and archive eligible selected orders. Every row in the order table must open the existing admin order detail page when clicked, while checkboxes and other controls keep their own behavior.

## Scope and assumptions

- The existing admin order list remains paginated and filtered. Selection applies to the rows currently rendered on the page; cross-page selection is intentionally out of scope for this iteration.
- “Gán nhiều đơn cho GHN” means setting `orders.shipping_provider` to `ghn` for each selected active order. It must not call the GHN API or create a real shipment; `orders.ghn_order_code` remains empty until a real tracking code is available later.
- Printing uses the browser print dialog. No PDF dependency is introduced.
- Archiving remains recoverable history retention through `archived_at`; it does not delete database rows.
- The current `SimpleXlsx` utility is reused for XLSX generation.
- The existing `GHNService` remains available for checkout/shipping flows, but this test-only bulk action does not call it.

## Current context

- `AdminOrderController` already owns the admin list, detail, status, and single-order archive flows.
- `Order` already has `ghn_district_id`, `ghn_ward_code`, `ghn_order_code`, `archived_at`, and `canBeDeletedByAdmin()`.
- `GHNService` already authenticates and calls GHN create/cancel endpoints.
- `SimpleXlsx` already creates a valid one-sheet workbook.
- The order list already renders row checkboxes and detail links, but the checkboxes do not yet submit a bulk action.

## Approaches considered

### 1. Controller-only implementation

Add four endpoints and put selection, local provider marking, export rows, and archive rules directly in `AdminOrderController`.

This is the smallest diff, but it would duplicate business rules inside a controller that already handles status, payment, cancellation, and archive flows. GHN partial failures would also be harder to test cleanly.

### 2. Focused bulk-action service with thin controller endpoints — recommended

Add a small `AdminOrderBulkActionService` responsible for loading selected orders, preparing printable/export data, marking the local GHN provider, and archiving only eligible orders. The controller validates the selected IDs, delegates, and returns a concise success/partial-failure message. The existing GHN integration remains untouched because this bulk action intentionally makes no remote request.

This keeps each action independently testable, makes partial GHN success explicit, and avoids adding a queue or new package before volume requires it.

### 3. Queue-backed bulk jobs

Dispatch one job per selected order and poll an operation-status table.

This is appropriate for hundreds or thousands of orders, but it requires new persistence, polling UI, retry semantics, and a worker deployment requirement. It is unnecessary for the current paginated admin list and can be introduced later behind the service interface if order volume grows.

## Design

### Admin table interaction

- Wrap the rendered table in one POST bulk form with CSRF protection.
- Keep the header checkbox as “select all visible rows”.
- Add a bulk toolbar with four actions: `In các đơn đã chọn`, `Xuất Excel`, `Gán GHN`, and `Lưu trữ`.
- Disable the toolbar actions until at least one row is selected and show the selected count.
- Use confirmation for GHN assignment and archiving.
- Give each `tbody` row a detail URL, keyboard focus, and Enter-key support. A click on the row opens `admin.orders.show`; clicks on `input`, `a`, `button`, `select`, `textarea`, or `label` are ignored so checkboxes and controls remain usable.
- Remove the old client-side “export current page” CSV behavior; the Excel action is server-generated and reflects exactly the checked rows.

### Routes and controller boundary

Add four admin POST routes under the existing admin middleware:

- `admin.orders.bulk.print`
- `admin.orders.bulk.export`
- `admin.orders.bulk.ghn`
- `admin.orders.bulk.archive`

Each route accepts `order_ids` as a required non-empty array of existing order IDs. The controller loads non-archived orders by those IDs and delegates to the service. A missing, archived, or unauthorized selection is rejected rather than silently operating on a different set.

### Printing

The print endpoint returns a dedicated `admin.orders.print` view with the selected orders and their items. The view contains one A4 packing slip per order and uses `@media print` with `page-break-after: always`; the last slip does not force an extra blank page. Each slip includes order number/date, recipient and address, product/variant/quantity/price, subtotal, shipping fee, total, payment method/status, and GHN tracking code when available. A small `window.print()` trigger runs after load, while a normal back link remains available outside the print area.

### Excel export

The export endpoint loads the selected orders with user and item relations and returns a download named `don-hang-da-chon-YYYY-MM-DD-HH-mm.xlsx`. The sheet contains one row per order with order ID, created date, status, recipient, phone, address, product summary, item quantity, subtotal, shipping fee, total, payment status, GHN code, and provider. Empty selections are validation errors; no temporary selection state is stored.

### GHN assignment

An order is eligible when it is not archived or cancelled/completed. The service updates only `shipping_provider = 'ghn'`; it does not require GHN address IDs, build a payload, contact GHN, or write `ghn_order_code`. Existing tracking codes are never overwritten. Legacy orders that already have a GHN code are displayed as GHN and can be synchronized to the provider field without another request.

### Bulk archive

The service uses the same `deletableByAdmin()` eligibility rules as the existing single-order archive action: cancelled orders or completed orders older than seven days, excluding orders with pending refunds or pending stock return. Eligible selected orders are locked and updated with `archived_at`. Ineligible selected orders are skipped and listed in the response summary; no order is physically deleted.

### Error handling and feedback

- Invalid selection: redirect back with validation errors and no mutation.
- GHN assignment: show `success/failed/skipped` counts and clearly state that the action only marks the provider locally; no external request is made.
- Archive partial eligibility: archive eligible rows and report skipped order numbers.
- Print/export: return a clear validation error for an empty selection and preserve filters on redirect where applicable.
- All bulk mutations are POST-only and protected by the existing admin authorization middleware and CSRF token.

## Testing strategy

- Feature test that the admin list renders row-level detail URLs and a row click hook without reintroducing an action column.
- Feature test that an admin can print selected orders and receives one slip per selected order.
- Feature test that selected orders export as XLSX with the expected download content type and order data.
- Feature test with `Http::fake()` proving selected active orders persist `shipping_provider = 'ghn'`, tracking codes stay empty, cancelled/completed orders are skipped, and no external request is sent.
- Feature test that only eligible selected orders receive `archived_at` and ineligible orders remain visible.
- Request/authorization coverage that non-admin users cannot call any bulk route and empty selections are rejected.
- Run the focused order/GHN tests, PHP syntax checks, `git diff --check`, and the frontend build before completion. Existing unrelated failures will be reported separately rather than changed incidentally.

## Out of scope

- Selecting orders across multiple pagination pages.
- Real GHN shipment creation, status polling/webhooks, and automatic order-status synchronization.
- Background queues, progress tracking, or retry dashboards.
- PDF generation or thermal-label formats.
- Changing the existing order detail cancellation flow.
