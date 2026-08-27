@extends('layouts.app')

@section('content')
<div class="orders-page">
    <h1>Đơn mua</h1>
    <nav class="order-tabs">
        <a href="{{ route('user.orders.index') }}" class="{{ $status === '' ? 'active' : '' }}">Tất cả</a>
        @foreach(['pending' => 'Chờ xác nhận', 'processing' => 'Đang xử lý', 'shipping' => 'Đang giao', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'] as $key => $label)
            <a href="{{ route('user.orders.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <div class="orders-search">⌕ <span>Bạn có thể tìm kiếm theo mã đơn hoặc tên sản phẩm</span></div>
    <div class="orders-list">
    @forelse($orders as $order)
        <article class="order-card">
            <header><strong>Đơn #{{ $order->id }}</strong><span>{{ $order->status_label }}</span></header>
            @foreach($order->items as $item)
                <div class="order-item"><div><strong>{{ $item->product_name }}</strong><p>{{ $item->variant_name }} · x{{ $item->quantity }}</p></div><b>{{ number_format($item->price) }} đ</b></div>
            @endforeach
            <div class="order-footer"><span>{{ $order->payment_label }}</span><strong>Tổng: {{ number_format($order->total) }} đ</strong></div>
        </article>
    @empty
        <div class="orders-empty"><div class="empty-icon">▣</div><p>Chưa có đơn hàng</p></div>
    @endforelse
    </div>
</div>
<style>
.orders-page{width:100%;padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 2px 8px rgba(15,23,42,.06);color:#334155}.orders-page h1{margin:0 0 18px;font-size:24px;color:#1e293b}.order-tabs{display:flex;gap:0;overflow-x:auto;border-bottom:1px solid #e2e8f0;margin-bottom:16px}.order-tabs a{padding:14px 20px;color:#475569;text-decoration:none;white-space:nowrap;border-bottom:2px solid transparent}.order-tabs a.active{color:#f45135;border-color:#f45135}.orders-search{padding:14px 18px;background:#f1f5f9;color:#94a3b8;margin-bottom:14px;border-radius:6px}.orders-search span{margin-left:10px}.order-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px;padding:16px;box-shadow:0 1px 3px rgba(15,23,42,.04)}.order-card header,.order-footer{display:flex;justify-content:space-between;gap:12px;padding-bottom:12px;border-bottom:1px solid #f1f5f9}.order-card header span{color:#f45135}.order-item{display:flex;justify-content:space-between;padding:14px 0;border-bottom:1px solid #f1f5f9}.order-item p{margin:6px 0 0;color:#64748b;font-size:13px}.order-footer{border:0;padding:14px 0 0;color:#64748b}.order-footer strong{color:#f45135;font-size:17px}.orders-empty{text-align:center;background:#fff;min-height:420px;padding-top:130px;color:#334155}.empty-icon{font-size:52px;color:#cbd5e1}.orders-empty p{font-size:18px}@media(max-width:600px){.orders-page{padding:16px}.order-tabs a{padding:12px 14px}.order-footer{align-items:flex-end;flex-direction:column}}
    .orders-page{min-height:calc(100vh - 130px);display:flex;flex-direction:column;padding:0;background:transparent;border:0;border-radius:0;box-shadow:none}
    .user-card:has(.orders-page){box-shadow:none}
    .orders-list .order-card{margin:0;padding:18px 8px;background:transparent;border:0;border-bottom:1px solid #e2e8f0;border-radius:0;box-shadow:none}
    .orders-list .order-card:last-child{border-bottom:0}
    .orders-list{height:auto;max-height:calc(100vh - 390px);flex:1;overflow-y:auto;padding-right:8px;scrollbar-gutter:stable}
    .orders-list::-webkit-scrollbar{width:9px}
    .orders-list::-webkit-scrollbar-track{background:#f1f5f9;border-radius:999px}
    .orders-list::-webkit-scrollbar-thumb{background:#94a3b8;border:2px solid #f1f5f9;border-radius:999px}
    .orders-list::-webkit-scrollbar-thumb:hover{background:#64748b}
    @media(max-width:600px){.orders-page{min-height:calc(100vh - 110px)}.orders-list{height:auto;max-height:65vh}}
</style>
@endsection
