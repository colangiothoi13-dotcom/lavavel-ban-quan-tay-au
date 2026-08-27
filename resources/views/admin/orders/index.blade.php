@extends('layouts.app')

@section('content')
<div class="admin-orders">
    <div class="orders-heading">
        <div><h1>Quản lý đơn hàng</h1><p>Danh sách đơn hàng khách hàng đã đặt trên hệ thống.</p></div>
        <div class="orders-heading-actions">
            @if($pendingCount > 0)
                <form method="POST" action="{{ route('admin.orders.confirm-all') }}">
                    @csrf @method('PATCH')
                    <button class="button" type="submit" onclick="return confirm('Xác nhận tất cả {{ $pendingCount }} đơn hàng đang chờ?')">Xác nhận tất cả ({{ $pendingCount }})</button>
                </form>
            @endif
            <strong>{{ $orders->count() }} đơn hàng</strong>
        </div>
    </div>

    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif

    <form class="filters" method="GET" action="{{ route('admin.orders.index') }}">
        <label>Trạng thái đơn
            <select name="status">
                <option value="">Tất cả</option>
                @foreach(['pending' => 'Chờ xác nhận', 'processing' => 'Đã xác nhận', 'shipping' => 'Đang giao', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'] as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Thanh toán
            <select name="payment_status">
                <option value="">Tất cả</option>
                <option value="paid" @selected($paymentStatus === 'paid')>Đã thanh toán</option>
                <option value="unpaid" @selected($paymentStatus === 'unpaid')>Chưa thanh toán</option>
            </select>
        </label>
        <button type="submit">Lọc đơn hàng</button>
    </form>

    <div class="order-list">
        @forelse($orders as $order)
            <article class="admin-order-card">
                <header>
                    <div><strong>Đơn #{{ $order->id }}</strong><span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
                    <b class="status status-{{ $order->status }}">{{ $order->status_label }}</b>
                </header>
                <div class="order-info">
                    <div><small>Khách hàng</small><strong>{{ $order->user?->name ?? $order->recipient_name }}</strong><span>{{ $order->phone }}</span></div>
                    <div><small>Địa chỉ giao hàng</small><span>{{ $order->address }}</span></div>
                    <div><small>Thanh toán</small><strong class="payment-{{ $order->payment_status }}">{{ $order->payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</strong><span>{{ $order->payment_label }}</span></div>
                    <div><small>Tổng tiền</small><strong class="total">{{ number_format($order->total, 0, ',', '.') }} đ</strong><span>{{ $order->items->sum('quantity') }} sản phẩm</span></div>
                </div>
                <div class="items">@foreach($order->items as $item)<span>{{ $item->product_name }} ({{ $item->variant_name }}) x{{ $item->quantity }}</span>@endforeach</div>
                <footer>
                    <form method="POST" action="{{ route('admin.orders.payment', $order) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="payment_status" value="{{ $order->payment_status === 'paid' ? 'unpaid' : 'paid' }}">
                        <button class="button button-light" type="submit">{{ $order->payment_status === 'paid' ? 'Đánh dấu chưa thanh toán' : 'Xác nhận đã thanh toán' }}</button>
                    </form>
                    @if($order->status === 'pending')
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="processing"><button class="button" type="submit">Xác nhận đơn</button></form>
                    @elseif($order->status === 'processing')
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="shipping"><button class="button" type="submit">Giao cho bên vận chuyển</button></form>
                    @elseif($order->status === 'shipping')
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="button button-success" type="submit">Xác nhận hoàn thành</button></form>
                    @endif
                </footer>
            </article>
        @empty
            <div class="empty">Không có đơn hàng phù hợp.</div>
        @endforelse
    </div>
</div>
<style>
.admin-orders{color:#1e293b}.orders-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:22px}.orders-heading h1{font-size:26px;margin:0 0 6px}.orders-heading p{color:#64748b;margin:0}.orders-heading-actions{display:flex;align-items:center;gap:14px}.orders-heading-actions form{margin:0}.orders-heading-actions>strong{color:#f4511e;white-space:nowrap}.notice{background:#dcfce7;color:#166534;padding:12px 16px;border-radius:6px;margin-bottom:18px}.filters{display:flex;align-items:end;gap:14px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;margin-bottom:20px}.filters label{display:grid;gap:6px;font-size:13px;font-weight:700}.filters select{min-width:180px;padding:10px;border:1px solid #cbd5e1;background:white;border-radius:4px;font:inherit}.button,.filters button{border:0;border-radius:5px;background:#f4511e;color:#fff;font-weight:700;padding:10px 14px;cursor:pointer}.button:hover,.filters button:hover{background:#d93d0e}.button-light{background:#fff;color:#475569;border:1px solid #cbd5e1}.button-light:hover{background:#f1f5f9;color:#1e293b}.button-success{background:#15803d}.button-success:hover{background:#166534}.admin-order-card{border:1px solid #e2e8f0;margin-bottom:14px;padding:18px;background:#fff}.admin-order-card header,.admin-order-card footer{display:flex;justify-content:space-between;align-items:center;gap:12px}.admin-order-card header{padding-bottom:14px;border-bottom:1px solid #f1f5f9}.admin-order-card header div{display:grid;gap:5px}.admin-order-card header span,.order-info span{color:#64748b;font-size:13px}.status{padding:6px 10px;border-radius:999px;font-size:13px}.status-pending{background:#fef3c7;color:#92400e}.status-processing{background:#dbeafe;color:#1d4ed8}.status-shipping{background:#e0e7ff;color:#4338ca}.status-completed{background:#dcfce7;color:#166534}.status-cancelled{background:#fee2e2;color:#991b1b}.order-info{display:grid;grid-template-columns:1fr 1.5fr 1fr 1fr;gap:18px;padding:16px 0}.order-info div{display:grid;gap:5px}.order-info small{font-weight:700;color:#64748b}.payment-paid{color:#15803d}.payment-unpaid{color:#b45309}.total{color:#f4511e;font-size:17px}.items{display:flex;flex-wrap:wrap;gap:8px;padding:12px 0;border-top:1px solid #f1f5f9;color:#475569;font-size:13px}.items span{background:#f8fafc;padding:7px 9px}.admin-order-card footer{justify-content:flex-end;padding-top:14px;border-top:1px solid #f1f5f9}.admin-order-card footer form{margin:0}.empty{text-align:center;color:#64748b;padding:80px;background:#f8fafc}@media(max-width:750px){.filters,.orders-heading,.orders-heading-actions,.admin-order-card footer{align-items:stretch;flex-direction:column}.order-info{grid-template-columns:1fr 1fr}.orders-heading-actions .button,.admin-order-card footer .button{width:100%}}
</style>
@endsection
