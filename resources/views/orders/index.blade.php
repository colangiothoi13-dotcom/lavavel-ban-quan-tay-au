@extends('layouts.app')

@section('content')
@php
    $tabs = [
        '' => 'Tất cả',
        'pending' => 'Chờ xác nhận',
        'shipping' => 'Đang giao',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];
@endphp

<div class="orders-page">
    <div class="orders-heading">
        <div>
            <span class="orders-eyebrow">Tài khoản của tôi</span>
            <h1>Đơn mua</h1>
            <p>Theo dõi trạng thái và xem lại các sản phẩm bạn đã đặt.</p>
        </div>
        <div class="orders-summary"><strong>{{ $statusCounts->sum() }}</strong><span>đơn hàng</span></div>
    </div>

    @if(session('status'))<div class="orders-notice">✓ {{ session('status') }}</div>@endif
    @if($errors->any())<div class="orders-error">{{ $errors->first() }}</div>@endif

    <nav class="order-tabs" aria-label="Lọc đơn hàng theo trạng thái">
        @foreach($tabs as $key => $label)
            @php
                $count = $key === '' ? $statusCounts->sum() : $statusCounts->get($key, 0);
            @endphp
            <a href="{{ route('user.orders.index', array_filter(['status' => $key, 'keyword' => $keyword])) }}" class="{{ $status === $key ? 'active' : '' }}">
                <span>{{ $label }}</span><b>{{ $count }}</b>
            </a>
        @endforeach
    </nav>

    <form class="orders-search" method="GET" action="{{ route('user.orders.index') }}">
        @if($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
        <input name="keyword" value="{{ $keyword }}" placeholder="Tìm theo mã đơn hoặc tên sản phẩm" aria-label="Tìm đơn hàng">
        <button type="submit">Tìm kiếm</button>
    </form>

    <div class="orders-list">
        @forelse($orders as $order)
            <article class="order-card">
                <header class="order-card-head">
                    <div>
                        <a href="{{ route('user.orders.show', $order) }}">Đơn hàng #{{ $order->id }}</a>
                        <span>Đặt lúc {{ $order->created_at->format('H:i, d/m/Y') }}</span>
                    </div>
                    <span class="order-status status-{{ $order->status }}">{{ $order->status_label }}</span>
                </header>

                <div class="order-products">
                    @foreach($order->items->take(2) as $item)
                        @php
                            $imagePath = $item->variant?->image ?: $item->variant?->product?->image;
                            $imageUrl = $imagePath
                                ? (str_starts_with($imagePath, 'http') ? $imagePath : asset('storage/'.$imagePath))
                                : null;
                        @endphp
                        <div class="order-product">
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $item->product_name }}">
                            @else
                                <div class="product-placeholder">SP</div>
                            @endif
                            <div class="product-copy">
                                <strong>{{ $item->product_name }}</strong>
                                <span>Phân loại: {{ str_replace(' - ', ' / ', $item->variant_name) }}</span>
                                <span>Số lượng: {{ $item->quantity }}</span>
                            </div>
                            <b>{{ number_format($item->price, 0, ',', '.') }} đ</b>
                        </div>
                    @endforeach
                    @if($order->items->count() > 2)
                        <div class="more-products">+ {{ $order->items->count() - 2 }} sản phẩm khác</div>
                    @endif
                </div>

                <footer class="order-card-foot">
                    <div class="payment-note"><span>{{ $order->payment_label }}</span><small>{{ $order->payment_status_label }}</small></div>
                    <div class="order-total"><span>Thành tiền</span><strong>{{ number_format($order->total, 0, ',', '.') }} đ</strong></div>
                    <div class="order-actions">
                        <a class="button button-light" href="{{ route('user.orders.show', $order) }}">Xem chi tiết</a>
                        @if(in_array($order->status, ['pending', 'processing', 'shipping'], true))
                            <button class="button button-danger" type="button" data-open-cancel-modal data-cancel-action="{{ route('user.orders.cancel', $order) }}">Hủy đơn</button>
                        @else
                            <form method="POST" action="{{ route('user.orders.reorder', $order) }}">
                                @csrf
                                <button class="button button-primary" type="submit">Mua lại</button>
                            </form>
                        @endif
                    </div>
                </footer>
            </article>
        @empty
            <div class="orders-empty">
                <div class="empty-bag">♧</div>
                <h2>Chưa tìm thấy đơn hàng</h2>
                <p>{{ $keyword !== '' ? 'Hãy thử mã đơn hoặc tên sản phẩm khác.' : 'Các đơn hàng của bạn sẽ xuất hiện tại đây.' }}</p>
                <a href="{{ route('shop.home') }}" class="button button-primary">Tiếp tục mua sắm</a>
            </div>
        @endforelse
    </div>
</div>

@include('orders.partials.cancel-modal')

<style>
    .orders-page{--navy:#1e293b;--gold:#b4860b;--muted:#64748b;--line:#e2e8f0;min-height:100%;color:#334155}
    .orders-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:22px}
    .orders-eyebrow{display:block;margin-bottom:5px;color:var(--gold);font-size:12px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}
    .orders-heading h1{margin:0 0 6px;color:var(--navy);font-size:27px}
    .orders-heading p{margin:0;color:var(--muted);font-size:14px}
    .orders-summary{display:grid;min-width:94px;padding:12px 18px;text-align:center;background:#f8fafc;border:1px solid var(--line);border-radius:10px}
    .orders-summary strong{color:var(--navy);font-size:22px}
    .orders-summary span{color:var(--muted);font-size:12px}
    .orders-notice,.orders-error{padding:12px 15px;margin-bottom:14px;border-radius:7px;font-size:14px}
    .orders-notice{background:#dcfce7;color:#166534}
    .orders-error{background:#fee2e2;color:#991b1b}
    .order-tabs{display:grid;grid-template-columns:repeat(5,1fr);border:1px solid var(--line);border-radius:9px 9px 0 0;background:#fff;overflow:hidden}
    .order-tabs a{display:flex;justify-content:center;align-items:center;gap:7px;padding:15px 8px;color:#64748b;text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap;font-size:14px}
    .order-tabs a:hover{background:#f8fafc;color:var(--navy)}
    .order-tabs a.active{color:var(--gold);border-bottom-color:var(--gold);font-weight:700}
    .order-tabs b{display:inline-grid;place-items:center;min-width:21px;height:21px;padding:0 6px;background:#f1f5f9;border-radius:999px;font-size:11px}
    .order-tabs a.active b{background:#fef3c7;color:#92400e}
    .orders-search{display:flex;align-items:center;gap:10px;margin:14px 0 18px;padding:0 5px 0 14px;background:#f8fafc;border:1px solid var(--line);border-radius:8px}
    .orders-search svg{width:19px;fill:none;stroke:#94a3b8;stroke-width:2;stroke-linecap:round}
    .orders-search input{flex:1;min-width:0;padding:12px 0;background:transparent;border:0;outline:0;font:inherit;color:var(--navy)}
    .orders-search button{padding:9px 17px;border:0;border-radius:6px;background:var(--navy);color:#fff;font-weight:700;cursor:pointer}
    .orders-list{display:grid;gap:15px}
    .order-card{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden;box-shadow:0 2px 7px rgba(15,23,42,.05)}
    .order-card-head{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:14px 18px;background:#f8fafc;border-bottom:1px solid var(--line)}
    .order-card-head>div{display:grid;gap:4px}
    .order-card-head a{color:var(--navy);font-weight:800;text-decoration:none}
    .order-card-head a:hover{color:var(--gold)}
    .order-card-head div span{color:var(--muted);font-size:12px}
    .order-status{padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800}
    .status-pending{background:#fef3c7;color:#92400e}.status-processing{background:#dbeafe;color:#1d4ed8}.status-shipping{background:#e0e7ff;color:#4338ca}.status-completed{background:#dcfce7;color:#166534}.status-cancelled{background:#fee2e2;color:#991b1b}
    .order-products{padding:0 18px}
    .order-product{display:grid;grid-template-columns:64px minmax(0,1fr) auto;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid #f1f5f9}
    .order-product img,.product-placeholder{width:64px;height:64px;border:1px solid var(--line);border-radius:7px;object-fit:cover;background:#f1f5f9}
    .product-placeholder{display:grid;place-items:center;color:#94a3b8;font-weight:800}
    .product-copy{display:grid;gap:4px;min-width:0}
    .product-copy strong{overflow:hidden;color:var(--navy);font-size:14px;text-overflow:ellipsis;white-space:nowrap}
    .product-copy span{color:var(--muted);font-size:12px}
    .order-product>b{color:#475569;font-size:14px;white-space:nowrap}
    .more-products{padding:10px 0;text-align:center;color:var(--muted);font-size:12px}
    .order-card-foot{display:grid;grid-template-columns:minmax(160px,1fr) auto auto;align-items:center;gap:22px;padding:15px 18px}
    .payment-note{display:grid;gap:3px;color:#475569;font-size:13px}.payment-note small{color:var(--muted)}
    .order-total{display:flex;align-items:baseline;gap:10px;white-space:nowrap}
    .order-total span{color:var(--muted);font-size:13px}.order-total strong{color:#ee4d2d;font-size:19px}
    .order-actions{display:flex;gap:8px}.order-actions form{margin:0}
    .button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 15px;border:1px solid transparent;border-radius:6px;text-decoration:none;font:700 13px Arial,sans-serif;cursor:pointer}
    .button-primary{background:var(--navy);color:#fff}.button-primary:hover{background:#0f172a}
    .button-light{background:#fff;color:#475569;border-color:#cbd5e1}.button-light:hover{background:#f8fafc}
    .button-danger{background:#fff;color:#dc2626;border-color:#fecaca}.button-danger:hover{background:#fef2f2}
    .orders-empty{padding:70px 20px;text-align:center;background:#fff;border:1px dashed #cbd5e1;border-radius:10px}
    .empty-bag{font-size:50px;color:#cbd5e1}.orders-empty h2{margin:10px 0 7px;color:var(--navy);font-size:19px}.orders-empty p{margin:0 0 20px;color:var(--muted)}
    @media(max-width:900px){.order-tabs{display:flex;overflow-x:auto}.order-tabs a{min-width:145px}.order-card-foot{grid-template-columns:1fr auto}.payment-note{display:none}.order-actions{grid-column:1/-1;justify-content:flex-end}}
    @media(max-width:600px){.orders-heading p,.orders-summary{display:none}.order-card-head{align-items:flex-start}.order-product{grid-template-columns:54px minmax(0,1fr)}.order-product img,.product-placeholder{width:54px;height:54px}.order-product>b{grid-column:2}.order-card-foot{grid-template-columns:1fr}.order-total{justify-content:space-between}.order-actions{grid-column:auto}.order-actions>*{flex:1}.order-actions .button{width:100%}.orders-search button{display:none}}
</style>
@endsection
