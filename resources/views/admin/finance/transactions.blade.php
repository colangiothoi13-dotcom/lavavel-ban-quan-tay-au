@extends('layouts.app')

@section('content')
@php
    $query = array_filter([
        'from' => $from,
        'to' => $to,
        'keyword' => $keyword,
        'min_amount' => $minAmount,
        'max_amount' => $maxAmount,
        'payment_method' => $paymentMethod,
        'payment_status' => $paymentStatus,
        'sort' => $sort,
    ], static fn ($value) => $value !== null && $value !== '');
@endphp

<div class="transactions-page">
    <div class="transactions-heading">
        <h1>Giao dịch thanh toán</h1>
        <p>Tra cứu thanh toán theo đơn hàng và cập nhật trạng thái COD.</p>
    </div>

    <nav class="transactions-tabs" aria-label="Khu vực tài chính">
        <a href="{{ route('admin.finance.index', $query) }}">Thống kê tài chính</a>
        <a class="active" href="{{ route('admin.finance.transactions', $query) }}">Giao dịch thanh toán</a>
    </nav>

    <form class="transactions-filter" method="GET" action="{{ route('admin.finance.transactions') }}">
        <div class="transactions-filter-row transactions-filter-row-three">
            <label>Tìm đơn hàng<input type="search" name="keyword" value="{{ $keyword }}" placeholder="Mã đơn, tên hoặc số điện thoại"></label>
            <label>Từ ngày tạo đơn<input type="date" name="from" value="{{ $from }}"></label>
            <label>Đến ngày tạo đơn<input type="date" name="to" value="{{ $to }}"></label>
        </div>
        <div class="transactions-filter-row transactions-filter-row-four">
            <label>Số tiền từ (đ)<input type="number" name="min_amount" value="{{ $minAmount }}" min="0" step="1" placeholder="Không giới hạn"></label>
            <label>Số tiền đến (đ)<input type="number" name="max_amount" value="{{ $maxAmount }}" min="0" step="1" placeholder="Không giới hạn"></label>
            <label>Phương thức<select name="payment_method"><option value="">Tất cả</option>@foreach($paymentMethodOptions as $option => $label)<option value="{{ $option }}" @selected($paymentMethod === $option)>{{ $label }}</option>@endforeach</select></label>
            <label>Trạng thái thanh toán<select name="payment_status"><option value="">Tất cả</option>@foreach($paymentStatusOptions as $option)<option value="{{ $option }}" @selected($paymentStatus === $option)>{{ $paymentStatusLabels[$option] ?? $option }}</option>@endforeach</select></label>
        </div>
        <div class="transactions-filter-row transactions-filter-row-sort">
            <label>Sắp xếp<select name="sort"><option value="latest" @selected($sort === 'latest')>Mới nhất</option><option value="oldest" @selected($sort === 'oldest')>Cũ nhất</option><option value="amount_desc" @selected($sort === 'amount_desc')>Số tiền cao nhất</option><option value="amount_asc" @selected($sort === 'amount_asc')>Số tiền thấp nhất</option></select></label>
            <div class="transactions-filter-actions"><button type="submit">Áp dụng bộ lọc</button><a href="{{ route('admin.finance.transactions') }}">Xóa bộ lọc</a></div>
        </div>
    </form>

    @if(session('status'))
        <div class="transactions-notice" role="status">{{ session('status') }}</div>
    @endif

    <p class="transactions-result-summary"><strong>{{ number_format($transactions->total()) }}</strong> đơn phù hợp bộ lọc. Số tiền bao gồm phí vận chuyển, ngày lọc là ngày tạo đơn.</p>

    <section class="transactions-panel">
        <div class="transactions-panel-heading">
            <strong>Danh sách giao dịch ({{ number_format($transactions->total()) }} đơn)</strong>
            <small>COD: xác nhận thu tiền thất bại; đơn đã thu tiền có thể chuyển sang chờ hoàn tiền rồi xác nhận đã hoàn tiền.</small>
        </div>
        <div class="transactions-table-wrap">
            <table class="transactions-table">
                <thead><tr><th>Đơn hàng</th><th>Khách hàng</th><th>Phương thức</th><th>Số tiền</th><th>Thanh toán</th><th>Cập nhật COD</th></tr></thead>
                <tbody>
                    @forelse($transactions as $order)
                        @php
                            $method = match ($order->payment_method) {
                                'cash', 'cod' => 'COD',
                                'momo', 'momo_atm', 'momo_cc' => 'MoMo',
                                'bank_transfer' => 'Chuyển khoản nội địa',
                                'international_transfer' => 'Chuyển khoản quốc tế',
                                default => $order->payment_method ?: 'Khác',
                            };
                            $hasFailedAttempt = $order->paymentTransactions->contains('status', 'failed');
                            $statusKey = $hasFailedAttempt ? 'failed' : ($order->payment_status === 'paid_refund_pending' ? 'refund_pending' : $order->payment_status);
                            $statusLabel = $paymentStatusLabels[$statusKey] ?? $order->payment_status_label;
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $order) }}">DH{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</a></td>
                            <td>{{ $order->recipient_name }}<small>{{ $order->phone }}</small></td>
                            <td>{{ $method }}</td>
                            <td><strong>{{ number_format($order->total, 0, ',', '.') }} đ</strong></td>
                            <td><span class="payment-status payment-status-{{ $statusKey }}">{{ $statusLabel }}</span></td>
                            <td>
                                @if($order->isCashPayment() && in_array($order->payment_status, ['paid', 'unpaid'], true))
                                    <form method="POST" action="{{ route('admin.finance.cod-payment', $order) }}" class="cod-payment-form">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="payment_status" value="{{ $order->payment_status === 'paid' ? 'unpaid' : 'paid' }}">
                                        <button type="submit">{{ $order->payment_status === 'paid' ? 'Đã thu COD' : 'Xác nhận COD' }}</button>
                                    </form>
                                @else
                                    <span class="transactions-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="transactions-empty">Không có đơn hàng phù hợp với bộ lọc.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($transactions->hasPages())
        <div class="transactions-pagination">{{ $transactions->onEachSide(1)->links() }}</div>
    @endif
</div>

<style>
    .transactions-page{--transactions-ink:#172033;--transactions-muted:#64748b;--transactions-line:#dbe2e9;color:var(--transactions-ink);font-family:Arial,sans-serif;font-size:10px}.transactions-heading{margin:15px 0 12px}.transactions-heading h1{margin:0 0 7px;font-size:18px;line-height:1.15}.transactions-heading p{margin:0;color:var(--transactions-muted);font-size:10px}
    .transactions-tabs{display:flex;align-items:center;gap:1px;height:38px;margin-bottom:20px;padding:4px;border:1px solid var(--transactions-line);border-radius:4px;background:#fff}.transactions-tabs a{display:inline-flex;align-items:center;height:28px;padding:0 12px;color:#1671cc;text-decoration:none;font-size:10px;font-weight:700;border-radius:3px}.transactions-tabs a.active{background:#4b5563;color:#fff}
    .transactions-filter{margin-bottom:18px;padding:13px 12px;border:1px solid var(--transactions-line);border-radius:4px;background:#fff}.transactions-filter-row{display:grid;gap:8px;margin-bottom:13px}.transactions-filter-row-three{grid-template-columns:repeat(3,minmax(0,1fr))}.transactions-filter-row-four{grid-template-columns:repeat(4,minmax(0,1fr))}.transactions-filter-row-sort{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:0}.transactions-filter label{display:grid;gap:5px;color:#64748b;font-size:10px}.transactions-filter input,.transactions-filter select{width:100%;height:28px;padding:0 8px;border:1px solid #d5dee7;border-radius:3px;background:#fff;color:#38506b;font:inherit;outline:none}.transactions-filter input::placeholder{color:#60758d}.transactions-filter input:focus,.transactions-filter select:focus{border-color:#7aa7d6;box-shadow:0 0 0 2px rgba(37,99,235,.08)}.transactions-filter-actions{display:flex;align-items:end;gap:7px;height:28px}.transactions-filter-actions button,.transactions-filter-actions a{height:27px;padding:0 10px;border:1px solid #087cf0;border-radius:3px;background:#087cf0;color:#fff;font:inherit;font-weight:700;line-height:25px;text-decoration:none;cursor:pointer}.transactions-filter-actions a{border-color:#9aa8b7;background:#fff;color:#64748b;font-weight:400}
    .transactions-result-summary{margin:0 0 13px;color:var(--transactions-muted);font-size:10px}.transactions-result-summary strong{font-weight:400}.transactions-panel{overflow:hidden;border:1px solid var(--transactions-line);border-radius:6px;background:#fff;box-shadow:0 0 0 1px rgba(148,163,184,.06)}.transactions-panel-heading{height:47px;padding:11px 12px 0;border-bottom:1px solid var(--transactions-line)}.transactions-panel-heading strong{display:block;margin-bottom:3px;color:#172033;font-size:11px}.transactions-panel-heading small{color:var(--transactions-muted);font-size:9px}.transactions-table-wrap{overflow-x:auto}.transactions-table{width:100%;min-width:760px;border-collapse:collapse;border-spacing:0}.transactions-table th,.transactions-table td{border-left:1px solid #edf1f5}.transactions-table th:first-child,.transactions-table td:first-child{border-left:0}.transactions-table th{height:34px;padding:0 9px;background:#e8edf2;border-bottom:1px solid var(--transactions-line);color:#52657b;text-align:left;font-size:9px;font-weight:400}.transactions-table td{height:45px;padding:0 9px;border-bottom:1px solid #edf1f5;color:#34475d;font-size:10px;vertical-align:middle}.transactions-table tbody tr:nth-child(even){background:#f7f9fb}.transactions-table tbody tr:last-child td{border-bottom:0}.transactions-table a{color:#1671cc;text-decoration:none;font-weight:700}.transactions-table td small{display:block;margin-top:3px;color:#94a3b8;font-size:9px}.transactions-table td:nth-child(4){color:#172033}.payment-status{display:inline-block;padding:4px 7px;border-radius:3px;font-size:9px;white-space:nowrap}.payment-status-paid{background:#dcfce7;color:#15803d}.payment-status-unpaid{background:#fff7ed;color:#c2410c}.payment-status-failed{background:#fee2e2;color:#b91c1c}.payment-status-refund_pending{background:#e0f2fe;color:#0369a1}.payment-status-refunded{background:#e0e7ff;color:#4338ca}.cod-payment-form{margin:0}.cod-payment-form button{height:25px;padding:0 8px;border:1px solid #cbd5e1;border-radius:3px;background:#fff;color:#52657b;font:inherit;font-size:9px;cursor:pointer}.cod-payment-form button:hover{border-color:#087cf0;color:#087cf0}.transactions-muted{color:#94a3b8}.transactions-empty{text-align:center!important;padding:20px!important;color:#64748b!important}.transactions-notice{margin-bottom:10px;padding:9px 11px;border:1px solid #bbf7d0;border-radius:3px;background:#f0fdf4;color:#166534}.transactions-pagination{display:flex;justify-content:flex-end;padding-top:10px}.transactions-pagination nav{font-size:10px}.transactions-pagination svg{width:13px;height:13px}.transactions-pagination a,.transactions-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:26px;padding:0 7px;border:1px solid var(--transactions-line);background:#fff;color:#52657b;text-decoration:none}.transactions-pagination span[aria-current=page]{border-color:#087cf0;background:#087cf0;color:#fff}
    @media(max-width:900px){.transactions-filter-row-three,.transactions-filter-row-four,.transactions-filter-row-sort{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.transactions-filter-row-three,.transactions-filter-row-four,.transactions-filter-row-sort{grid-template-columns:1fr}.transactions-tabs{overflow-x:auto}.transactions-tabs a{white-space:nowrap}.transactions-filter-actions{align-items:center}}
</style>
@endsection
