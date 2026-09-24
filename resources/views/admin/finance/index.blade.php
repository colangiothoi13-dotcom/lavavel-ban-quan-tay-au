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
    ], static fn ($value) => $value !== null && $value !== '');
@endphp

<div class="finance-page">
    <div class="finance-heading">
        <h1>Thống kê tài chính</h1>
        <p>Tổng hợp giá trị thanh toán theo trạng thái và phương thức.</p>
    </div>

    <nav class="finance-tabs" aria-label="Khu vực tài chính">
        <a class="active" href="{{ route('admin.finance.index', $query) }}">Thống kê tài chính</a>
        <a href="{{ route('admin.finance.transactions', $query) }}">Giao dịch thanh toán</a>
    </nav>

    <form class="finance-filter" method="GET" action="{{ route('admin.finance.index') }}">
        <div class="finance-filter-row finance-filter-row-three">
            <label>Tìm đơn hàng<input type="search" name="keyword" value="{{ $keyword }}" placeholder="Mã đơn, tên hoặc số điện thoại"></label>
            <label>Từ ngày tạo đơn<input type="date" name="from" value="{{ $from }}"></label>
            <label>Đến ngày tạo đơn<input type="date" name="to" value="{{ $to }}"></label>
        </div>
        <div class="finance-filter-row finance-filter-row-four">
            <label>Số tiền từ (đ)<input type="number" name="min_amount" value="{{ $minAmount }}" min="0" step="1" placeholder="Không giới hạn"></label>
            <label>Số tiền đến (đ)<input type="number" name="max_amount" value="{{ $maxAmount }}" min="0" step="1" placeholder="Không giới hạn"></label>
            <label>Phương thức<select name="payment_method"><option value="">Tất cả</option>@foreach($paymentMethodOptions as $option => $label)<option value="{{ $option }}" @selected($paymentMethod === $option)>{{ $label }}</option>@endforeach</select></label>
            <label>Trạng thái thanh toán<select name="payment_status"><option value="">Tất cả</option>@foreach($paymentStatusOptions as $option)<option value="{{ $option }}" @selected($paymentStatus === $option)>{{ $paymentStatusLabels[$option] ?? $option }}</option>@endforeach</select></label>
        </div>
        <div class="finance-filter-actions">
            <button type="submit">Áp dụng bộ lọc</button>
            <a href="{{ route('admin.finance.index') }}">Xóa bộ lọc</a>
        </div>
    </form>

    <p class="finance-result-summary"><strong>{{ number_format($ordersCount) }}</strong> đơn phù hợp. Số tiền bao gồm phí vận chuyển, thống kê theo ngày tạo đơn trên toàn bộ kết quả lọc.</p>

    <section class="finance-summary-grid" aria-label="Tổng hợp tài chính">
        @foreach($summaryCards as $card)
            <article class="finance-summary-card finance-tone-{{ $card['tone'] }}">
                <span>{{ $card['label'] }}</span>
                <strong>{{ number_format($card['amount'], 0, ',', '.') }} đ</strong>
                <small>{{ number_format($card['count']) }} {{ $card['hint'] }}</small>
            </article>
        @endforeach
    </section>

    <section class="finance-method-panel">
        <div class="finance-panel-title">Thống kê theo phương thức</div>
        <div class="finance-table-wrap">
            <table class="finance-method-table">
                <thead><tr><th>Phương thức</th><th>Số đơn</th><th>Tổng giá trị</th><th>Đã thanh toán</th></tr></thead>
                <tbody>
                    @foreach($paymentMethodStats as $stat)
                        <tr>
                            <td>{{ $stat['label'] }}</td>
                            <td>{{ number_format($stat['count']) }}</td>
                            <td>{{ number_format($stat['total'], 0, ',', '.') }} đ</td>
                            <td>{{ number_format($stat['paid'], 0, ',', '.') }} đ</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>

<style>
    .finance-page{--finance-ink:#172033;--finance-muted:#64748b;--finance-line:#dbe2e9;--finance-panel:#fff;color:var(--finance-ink);font-family:Arial,sans-serif;font-size:10px}
    .finance-heading{margin:15px 0 12px}.finance-heading h1{margin:0 0 7px;font-size:18px;line-height:1.15}.finance-heading p{margin:0;color:#64748b;font-size:10px}
    .finance-tabs{display:flex;align-items:center;gap:1px;height:38px;margin-bottom:20px;padding:4px;border:1px solid var(--finance-line);border-radius:4px;background:#fff}.finance-tabs a{display:inline-flex;align-items:center;height:28px;padding:0 12px;color:#1671cc;text-decoration:none;font-size:10px;font-weight:700;border-radius:3px}.finance-tabs a.active{background:#4b5563;color:#fff}
    .finance-filter{margin-bottom:20px;padding:13px 12px;border:1px solid var(--finance-line);border-radius:4px;background:var(--finance-panel)}.finance-filter-row{display:grid;gap:8px;margin-bottom:13px}.finance-filter-row-three{grid-template-columns:repeat(3,minmax(0,1fr))}.finance-filter-row-four{grid-template-columns:repeat(4,minmax(0,1fr))}.finance-filter label{display:grid;gap:5px;color:#64748b;font-size:10px}.finance-filter input,.finance-filter select{width:100%;height:28px;padding:0 8px;border:1px solid #d5dee7;border-radius:3px;background:#fff;color:#38506b;font:inherit;outline:none}.finance-filter input:focus,.finance-filter select:focus{border-color:#7aa7d6;box-shadow:0 0 0 2px rgba(37,99,235,.08)}.finance-filter input::placeholder{color:#60758d}.finance-filter-actions{display:flex;gap:7px;align-items:center}.finance-filter-actions button,.finance-filter-actions a{height:27px;padding:0 10px;border:1px solid #087cf0;border-radius:3px;background:#087cf0;color:#fff;font:inherit;font-weight:700;line-height:25px;text-decoration:none;cursor:pointer}.finance-filter-actions a{border-color:#9aa8b7;background:#fff;color:#64748b;font-weight:400}
    .finance-result-summary{margin:0 0 13px;color:#64748b;font-size:10px}.finance-result-summary strong{font-weight:400}
    .finance-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px 24px;margin-bottom:14px}.finance-summary-card{height:86px;padding:14px 13px;border:1px solid var(--finance-line);border-radius:6px;background:#fff;box-shadow:0 0 0 1px rgba(148,163,184,.08);}.finance-summary-card span,.finance-summary-card small{display:block}.finance-summary-card span{margin-bottom:7px;color:#64748b}.finance-summary-card strong{display:block;margin-bottom:6px;color:#172033;font-size:14px;line-height:1}.finance-summary-card small{color:#172033;font-size:9px}.finance-summary-card.finance-tone-orange{border-left:4px solid #f59e0b}.finance-summary-card.finance-tone-green{border-left:4px solid #16a34a}.finance-summary-card.finance-tone-red{border-left:4px solid #ef3340}.finance-summary-card.finance-tone-cyan{border-left:4px solid #0891b2}.finance-summary-card.finance-tone-orange strong{color:#f59e0b}.finance-summary-card.finance-tone-green strong{color:#16a34a}.finance-summary-card.finance-tone-red strong{color:#ef3340}.finance-summary-card.finance-tone-cyan strong{color:#0891b2}
    .finance-method-panel{overflow:hidden;border:1px solid var(--finance-line);border-radius:6px;background:#fff;box-shadow:0 0 0 1px rgba(148,163,184,.06)}.finance-panel-title{height:35px;padding:11px 12px 0;color:#172033;font-size:11px;font-weight:700}.finance-table-wrap{overflow-x:auto}.finance-method-table{width:100%;border-collapse:collapse;border-spacing:0}.finance-method-table th,.finance-method-table td{border-left:1px solid #edf1f5}.finance-method-table th:first-child,.finance-method-table td:first-child{border-left:0}.finance-method-table th{height:35px;padding:0 9px;background:#e8edf2;border-top:1px solid var(--finance-line);border-bottom:1px solid var(--finance-line);color:#52657b;text-align:left;font-size:9px;font-weight:400}.finance-method-table td{height:36px;padding:0 9px;border-bottom:1px solid #edf1f5;color:#34475d;font-size:10px}.finance-method-table td:nth-child(n+2),.finance-method-table th:nth-child(n+2){text-align:right}.finance-method-table tbody tr:nth-child(even){background:#f7f9fb}.finance-method-table tbody tr:last-child td{border-bottom:0}
    @media(max-width:900px){.finance-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-filter-row-three,.finance-filter-row-four{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:560px){.finance-summary-grid,.finance-filter-row-three,.finance-filter-row-four{grid-template-columns:1fr}.finance-tabs{overflow-x:auto}.finance-tabs a{white-space:nowrap}}
</style>
@endsection
