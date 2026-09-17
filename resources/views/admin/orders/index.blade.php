@extends('layouts.app')

@section('content')
@php
    $tabItems = [
        '' => 'Tất cả',
        'pending' => 'Chờ xử lý',
        'processing' => 'Chờ lấy hàng',
        'shipping' => 'Đang giao',
        'completed' => 'Thành công',
        'cancelled' => 'Đã hủy',
    ];
    $tabQuery = array_filter([
        'payment_status' => $paymentStatus,
        'keyword' => $keyword,
        'per_page' => $perPage,
    ], fn ($value) => $value !== '' && $value !== null);
@endphp

<div class="admin-orders">
    <div class="order-breadcrumb"><span>Quản trị</span><b>/</b><strong>Đơn hàng</strong></div>

    <form class="orders-toolbar" method="GET" action="{{ route('admin.orders.index') }}">
        <div class="toolbar-heading">
            <h1>Đơn hàng</h1>
            <span>{{ number_format($orders->total()) }} đơn hàng</span>
        </div>

        <input type="hidden" name="status" value="{{ $status }}">
        <div class="toolbar-pagination" aria-label="Phân trang đơn hàng">
            @if($orders->onFirstPage())
                <span class="page-arrow is-disabled" aria-hidden="true">‹</span>
            @else
                <a class="page-arrow" href="{{ $orders->previousPageUrl() }}" aria-label="Trang trước">‹</a>
            @endif
            <span>{{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} / {{ $orders->total() }}</span>
            @if($orders->hasMorePages())
                <a class="page-arrow" href="{{ $orders->nextPageUrl() }}" aria-label="Trang sau">›</a>
            @else
                <span class="page-arrow is-disabled" aria-hidden="true">›</span>
            @endif
        </div>

        <label class="toolbar-select">
            <span>Hiển thị</span>
            <select name="per_page" onchange="this.form.submit()" aria-label="Số đơn mỗi trang">
                @foreach([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>
        <label class="toolbar-select toolbar-payment">
            <span>Thanh toán</span>
            <select name="payment_status" aria-label="Lọc trạng thái thanh toán">
                <option value="">Tất cả</option>
                <option value="paid" @selected($paymentStatus === 'paid')>Đã thanh toán</option>
                <option value="paid_refund_pending" @selected($paymentStatus === 'paid_refund_pending')>Chờ hoàn tiền thừa</option>
                <option value="unpaid" @selected($paymentStatus === 'unpaid')>Chưa thanh toán</option>
                <option value="refund_pending" @selected($paymentStatus === 'refund_pending')>Chờ hoàn tiền</option>
                <option value="refunded" @selected($paymentStatus === 'refunded')>Đã hoàn tiền</option>
            </select>
        </label>
        <label class="toolbar-search">
            <span class="sr-only">Tìm kiếm đơn hàng</span>
            <input name="keyword" value="{{ $keyword }}" placeholder="Mã đơn, khách hàng, SĐT, sản phẩm..." aria-label="Tìm kiếm đơn hàng">
            <button type="submit" aria-label="Tìm kiếm">⌕</button>
        </label>
        <a class="toolbar-refresh" href="{{ route('admin.orders.index', ['status' => $status]) }}" aria-label="Xóa bộ lọc">↻</a>
    </form>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif

    <nav class="admin-order-tabs" aria-label="Bộ lọc trạng thái đơn hàng">
        @foreach($tabItems as $key => $label)
            <a href="{{ route('admin.orders.index', array_merge(['status' => $key], $tabQuery)) }}" class="{{ $status === $key ? 'active' : '' }}">
                <span>{{ $label }}</span><strong>{{ $statusCounts->get($key, 0) }}</strong>
            </a>
        @endforeach
    </nav>

    <div class="orders-summary-bar">
        <span>{{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} trong {{ number_format($orders->total()) }} đơn hàng</span>
        <span>Gán GHN ở đây chỉ đánh dấu nội bộ, chưa tạo vận đơn thật</span>
    </div>

    <div class="orders-bulk-toolbar" aria-label="Xử lý nhiều đơn hàng">
        <span class="bulk-selection"><strong data-selected-count>0</strong> đơn đã chọn</span>
        <div class="bulk-actions">
            <button type="submit" form="bulk-order-form" formaction="{{ route('admin.orders.bulk.print') }}" formtarget="_blank" class="bulk-button bulk-button-primary" data-bulk-action>In các đơn đã chọn</button>
            <button type="submit" form="bulk-order-form" formaction="{{ route('admin.orders.bulk.export') }}" class="bulk-button" data-bulk-action>Xuất Excel</button>
            <button type="submit" form="bulk-order-form" formaction="{{ route('admin.orders.bulk.ghn') }}" class="bulk-button" data-bulk-action data-confirm="Bạn có chắc muốn đánh dấu các đơn đã chọn là GHN không? Chưa có request tạo vận đơn thật.">Gán GHN</button>
            <button type="submit" form="bulk-order-form" formaction="{{ route('admin.orders.bulk.archive') }}" class="bulk-button bulk-button-danger" data-bulk-action data-confirm="Chỉ các đơn đủ điều kiện mới được lưu trữ. Bạn vẫn muốn tiếp tục?">Lưu trữ</button>
        </div>
    </div>

    <form id="bulk-order-form" method="POST" action="{{ route('admin.orders.bulk.print') }}">
        @csrf
        <div class="orders-table-wrap">
            <table class="orders-table">
            <thead>
                <tr>
                    <th class="check-column"><span class="sr-only">Chọn</span><input type="checkbox" data-select-all aria-label="Chọn tất cả đơn hàng"></th>
                    <th>Mã đơn hàng</th>
                    <th>Ngày tạo đơn</th>
                    <th class="product-column">Sản phẩm</th>
                    <th>Tổng tiền</th>
                    <th>COD cần thu</th>
                    <th>Tên khách hàng</th>
                    <th>Mã vận đơn</th>
                    <th>Trạng thái giao hàng</th>
                    <th>Đơn vị VC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    @php
                        $codAmount = $order->isCashPayment() && $order->payment_status !== 'paid' ? (float) $order->total : 0;
                        $customerName = $order->user?->name ?? $order->recipient_name;
                        $shippingProvider = $order->shipping_provider ?: ($order->ghn_order_code ? 'ghn' : null);
                    @endphp
                    <tr data-order-row data-order-url="{{ route('admin.orders.show', $order) }}" tabindex="0">
                        <td class="check-column"><input type="checkbox" name="order_ids[]" value="{{ $order->id }}" data-order-checkbox aria-label="Chọn đơn DH{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}"></td>
                        <td data-export-cell>
                            <a class="order-code" href="{{ route('admin.orders.show', $order) }}">DH{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</a>
                            <span class="payment-badge payment-{{ $order->payment_status }}">{{ $order->payment_status_label }}</span>
                        </td>
                        <td data-export-cell>
                            <strong>{{ $order->created_at->format('d/m/Y') }}</strong>
                            <small>{{ $order->created_at->format('H:i') }}</small>
                        </td>
                        <td class="product-column" data-export-cell>
                            @foreach($order->items->take(2) as $item)
                                @php
                                    $imagePath = $item->variant?->image ?: $item->variant?->product?->image;
                                    $imageUrl = $imagePath
                                        ? (str_starts_with($imagePath, 'http') ? $imagePath : asset('storage/'.$imagePath))
                                        : null;
                                @endphp
                                <span class="product-line">
                                    @if($imageUrl)<img class="product-thumb" src="{{ $imageUrl }}" alt="">@endif
                                    <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                                </span>
                            @endforeach
                            @if($order->items->count() > 2)
                                <small>+ {{ $order->items->count() - 2 }} sản phẩm khác</small>
                            @elseif($order->items->isEmpty())
                                <small>Chưa có sản phẩm</small>
                            @endif
                        </td>
                        <td data-export-cell><strong class="money">{{ number_format($order->total, 0, ',', '.') }}</strong><small>đ</small></td>
                        <td data-export-cell><strong class="cod-money">{{ number_format($codAmount, 0, ',', '.') }}</strong><small>đ</small></td>
                        <td data-export-cell>
                            <strong>{{ $customerName }}</strong>
                            <small>{{ $order->phone }}</small>
                        </td>
                        <td data-export-cell>
                            @if($order->ghn_order_code)
                                <span class="tracking-code">{{ $order->ghn_order_code }}</span>
                            @else
                                <small>Chưa có vận đơn</small>
                            @endif
                        </td>
                        <td data-export-cell><span class="status status-{{ $order->status }}">● {{ $order->status_label }}</span></td>
                        <td data-export-cell>{{ $shippingProvider === 'ghn' ? 'GHN' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="empty" colspan="10">Không có đơn hàng phù hợp.</td>
                    </tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </form>

    @if($orders->hasPages())
        <div class="orders-pagination">{{ $orders->onEachSide(1)->links() }}</div>
    @endif
</div>

<style>
    .admin-orders{--orange:#f4511e;--ink:#334155;--muted:#64748b;--line:#e2e8f0;--soft:#f8fafc;color:var(--ink);font-family:Arial,sans-serif;font-size:12px}
    .admin-orders .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .order-breadcrumb{display:flex;align-items:center;gap:7px;margin:1px 0 13px;color:#94a3b8;font-size:11px}.order-breadcrumb b{font-weight:400;color:#cbd5e1}.order-breadcrumb strong{color:var(--ink)}
    .orders-toolbar{display:flex;align-items:center;gap:8px;padding:0 0 10px;border-bottom:1px solid #edf2f7;flex-wrap:wrap}.toolbar-heading{display:flex;align-items:baseline;gap:10px;margin-right:auto}.toolbar-heading h1{margin:0;color:#26364a;font-size:16px}.toolbar-heading span{color:#94a3b8;font-size:11px}.toolbar-pagination{display:flex;align-items:center;gap:7px;color:#64748b;white-space:nowrap;font-size:11px}.page-arrow{display:inline-grid;place-items:center;width:22px;height:22px;border:1px solid var(--line);border-radius:3px;background:#fff;color:#64748b;text-decoration:none;font-size:16px;line-height:1}.page-arrow.is-disabled{color:#cbd5e1;background:#f8fafc}.toolbar-select{display:flex;align-items:center;gap:5px;color:#64748b;white-space:nowrap}.toolbar-select select{height:25px;padding:0 6px;border:1px solid var(--line);border-radius:3px;background:#fff;color:#475569;font-size:11px}.toolbar-search{display:flex;align-items:center;width:218px;height:25px;border:1px solid var(--line);border-radius:3px;background:#fff}.toolbar-search input{min-width:0;flex:1;height:100%;padding:0 8px;border:0;outline:0;color:var(--ink);font-size:11px}.toolbar-search button{width:28px;height:100%;border:0;border-left:1px solid #edf2f7;background:#fff;color:#94a3b8;font-size:17px;cursor:pointer}.toolbar-refresh{display:inline-grid;place-items:center;width:25px;height:25px;border:1px solid var(--line);border-radius:3px;background:#fff;color:#64748b;text-decoration:none;cursor:pointer;font-size:15px}
    .admin-orders .notice{padding:9px 12px;margin:10px 0;border:1px solid #bbf7d0;border-radius:4px;background:#f0fdf4;color:#166534}.admin-order-tabs{display:flex;align-items:stretch;margin-top:10px;border-bottom:1px solid var(--line);overflow-x:auto}.admin-order-tabs a{display:flex;align-items:center;gap:5px;padding:10px 12px;color:#64748b;text-decoration:none;border-bottom:2px solid transparent;white-space:nowrap;font-size:10px;font-weight:700;text-transform:uppercase}.admin-order-tabs a:hover{background:#f8fafc;color:#334155}.admin-order-tabs a.active{color:#198754;border-bottom-color:#198754}.admin-order-tabs a strong{display:inline-grid;place-items:center;min-width:15px;height:15px;padding:0 4px;border:1px solid #cbd5e1;border-radius:3px;background:#fff;color:#64748b;font-size:9px}.admin-order-tabs a.active strong{border-color:#198754;background:#ecfdf5;color:#198754}
    .orders-summary-bar{display:flex;justify-content:space-between;align-items:center;padding:8px 5px;color:#94a3b8;font-size:10px}.orders-bulk-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;padding:8px 10px;border:1px solid var(--line);border-radius:4px;background:#f8fafc}.bulk-selection{color:#64748b;font-size:10px}.bulk-selection strong{color:#334155}.bulk-actions{display:flex;flex-wrap:wrap;gap:6px}.bulk-button{height:26px;padding:0 10px;border:1px solid #cbd5e1;border-radius:3px;background:#fff;color:#475569;font-size:10px;font-weight:700;cursor:pointer}.bulk-button:hover:not(:disabled){border-color:#198754;color:#198754}.bulk-button:disabled{cursor:not-allowed;opacity:.45}.bulk-button-primary{border-color:#198754;background:#198754;color:#fff}.bulk-button-primary:hover:not(:disabled){background:#157347;color:#fff}.bulk-button-danger{border-color:#fecaca;color:#b91c1c}.bulk-button-danger:hover:not(:disabled){border-color:#ef4444;color:#b91c1c}.orders-table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:3px}.orders-table{width:100%;min-width:1240px;border-collapse:collapse;background:#fff;table-layout:fixed}.orders-table th{height:32px;padding:6px 8px;background:#e9eef3;color:#607083;text-align:left;font-size:9px;font-weight:700;white-space:nowrap}.orders-table td{height:54px;padding:7px 8px;border-top:1px solid #eef2f6;vertical-align:middle;color:#475569;font-size:10px}.orders-table tbody tr:nth-child(even){background:#f8fafc}.orders-table tbody tr:hover,.orders-table tbody tr:focus{background:#f1f7fb}.orders-table tbody tr[data-order-row]{cursor:pointer;outline:none}.orders-table tbody tr[data-order-row]:focus{box-shadow:inset 0 0 0 2px rgba(25,135,84,.35)}.orders-table th:nth-child(1),.orders-table td:nth-child(1){width:32px}.orders-table th:nth-child(2),.orders-table td:nth-child(2){width:105px}.orders-table th:nth-child(3),.orders-table td:nth-child(3){width:82px}.orders-table th:nth-child(4),.orders-table td:nth-child(4){width:175px}.orders-table th:nth-child(5),.orders-table td:nth-child(5){width:76px}.orders-table th:nth-child(6),.orders-table td:nth-child(6){width:75px}.orders-table th:nth-child(7),.orders-table td:nth-child(7){width:120px}.orders-table th:nth-child(8),.orders-table td:nth-child(8){width:100px}.orders-table th:nth-child(9),.orders-table td:nth-child(9){width:115px}.orders-table th:nth-child(10),.orders-table td:nth-child(10){width:54px}.check-column{text-align:center!important}.orders-table input[type=checkbox]{width:12px;height:12px;accent-color:#198754}.orders-table td>strong,.orders-table td>small,.product-line{display:block}.orders-table td>strong{color:#334155;font-size:10px}.orders-table td>small{margin-top:3px;color:#94a3b8;font-size:9px}.order-code,.tracking-code{display:block;color:#1677c8;text-decoration:none;font-weight:700}.order-code:hover,.tracking-code:hover{text-decoration:underline}.payment-badge{display:inline-block;margin-top:4px;padding:2px 4px;border-radius:2px;font-size:8px;font-weight:700}.payment-paid{background:#d1fae5;color:#047857}.payment-unpaid{background:#ffedd5;color:#c2410c}.payment-refund_pending,.payment-paid_refund_pending{background:#fef3c7;color:#a16207}.payment-refunded{background:#e0e7ff;color:#4338ca}.product-column{overflow:hidden}.product-line{overflow:hidden;color:#3984c7;text-overflow:ellipsis;white-space:nowrap}.product-thumb{width:18px;height:18px;margin-right:3px;border:1px solid #dbe3eb;border-radius:2px;object-fit:cover;vertical-align:middle}.money{color:#475569!important}.cod-money{color:#64748b!important}.status{display:inline-flex;align-items:center;gap:3px;font-weight:700;white-space:nowrap}.status-pending{color:#d97706}.status-processing{color:#2563eb}.status-shipping{color:#2563eb}.status-completed{color:#16a34a}.status-cancelled{color:#ef4444}.empty{text-align:center!important;padding:40px!important;color:#94a3b8!important}.orders-pagination{display:flex;justify-content:flex-end;padding-top:12px}.orders-pagination nav{font-size:11px}.orders-pagination svg{width:14px;height:14px}.orders-pagination a,.orders-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 7px;border:1px solid var(--line);background:#fff;color:#475569;text-decoration:none}.orders-pagination span[aria-current=page]{border-color:#198754;background:#198754;color:#fff}
    @media(max-width:1100px){.toolbar-heading{width:100%;margin-right:0}.orders-toolbar{align-items:stretch}.toolbar-search{flex:1;min-width:180px}.toolbar-payment{margin-left:auto}}
    @media(max-width:640px){.orders-summary-bar{align-items:flex-start;gap:5px;flex-direction:column}.toolbar-payment{margin-left:0}.orders-bulk-toolbar{align-items:flex-start;flex-direction:column}.bulk-actions{width:100%}.bulk-button{flex:1}.toolbar-refresh{margin-left:auto}}
</style>

<script>
    (function () {
        const form = document.getElementById('bulk-order-form');
        if (!form) return;

        const selectAll = form.querySelector('[data-select-all]');
        const checkboxes = Array.from(form.querySelectorAll('[data-order-checkbox]'));
        const bulkActions = Array.from(document.querySelectorAll('[data-bulk-action]'));
        const selectedCount = document.querySelector('[data-selected-count]');

        function syncSelection() {
            const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
            selectedCount.textContent = selected;
            bulkActions.forEach((button) => { button.disabled = selected === 0; });
            selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
            selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
        }

        selectAll.addEventListener('change', function () {
            checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            syncSelection();
        });
        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', syncSelection));

        form.addEventListener('submit', function (event) {
            const submitter = event.submitter;
            if (!submitter || !submitter.dataset.confirm) return;

            if (!window.confirm(submitter.dataset.confirm)) event.preventDefault();
        });

        document.querySelectorAll('tr[data-order-row]').forEach((row) => {
            const navigate = (event) => {
                if (event.target.closest('a,button,input,select,textarea,label')) return;
                window.location.href = row.dataset.orderUrl;
            };

            row.addEventListener('click', navigate);
            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                navigate(event);
            });
        });

        syncSelection();
    }());
</script>
@endsection
