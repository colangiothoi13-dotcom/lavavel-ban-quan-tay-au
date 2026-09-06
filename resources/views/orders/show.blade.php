@extends('layouts.app')

@section('content')
@php
    $steps = [
        ['label' => 'Đã đặt hàng', 'hint' => $order->created_at->format('d/m/Y H:i')],
        ['label' => 'Đã xác nhận', 'hint' => 'Đang chuẩn bị hàng'],
        ['label' => 'Đang giao', 'hint' => 'Đơn vị vận chuyển'],
        ['label' => 'Hoàn thành', 'hint' => $order->completed_at?->format('d/m/Y H:i') ?? 'Đã nhận hàng'],
    ];
    $currentStep = ['pending' => 0, 'processing' => 1, 'shipping' => 2, 'completed' => 3][$order->status] ?? -1;
@endphp

<div class="order-detail-page">
    <div class="detail-topbar">
        <a href="{{ route('user.orders.index') }}">← Trở lại đơn mua</a>
        <span>Đơn hàng #{{ $order->id }}</span>
        <span class="detail-status status-{{ $order->status }}">{{ $order->status_label }}</span>
    </div>

    @if($errors->any())<div class="detail-error">{{ $errors->first() }}</div>@endif

    @if($order->status === 'cancelled')
        <section class="cancelled-banner">
            <div class="cancel-icon">×</div>
            <div>
                <h1>Đơn hàng đã hủy</h1>
                <p>Sản phẩm của đơn đã được hoàn lại kho. Bạn có thể mua lại bất cứ lúc nào.</p>
                @if($order->cancellation_reason)<strong>Lý do: {{ $order->cancellation_reason }}</strong>@endif
            </div>
        </section>
    @else
        <section class="tracking-card">
            <div class="section-heading"><div><span>TIẾN TRÌNH GIAO HÀNG</span><h1>Đơn hàng đang được xử lý</h1></div><small>Cập nhật gần nhất: {{ $order->updated_at->format('H:i, d/m/Y') }}</small></div>
            <div class="tracking-steps">
                @foreach($steps as $index => $step)
                    <div class="tracking-step {{ $index <= $currentStep ? 'done' : '' }} {{ $index === $currentStep ? 'current' : '' }}">
                        <div class="step-dot">@if($index < $currentStep || $currentStep === 3)✓@else{{ $index + 1 }}@endif</div>
                        <strong>{{ $step['label'] }}</strong>
                        <span>{{ $step['hint'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="detail-grid">
        <main>
            <section class="detail-card products-card">
                <div class="card-title"><h2>Sản phẩm trong đơn</h2><span>{{ $order->items->sum('quantity') }} sản phẩm</span></div>
                @foreach($order->items as $item)
                    @php
                        $imagePath = $item->variant?->image ?: $item->variant?->product?->image;
                        $imageUrl = $imagePath
                            ? (str_starts_with($imagePath, 'http') ? $imagePath : asset('storage/'.$imagePath))
                            : null;
                    @endphp
                    <div class="detail-product">
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $item->product_name }}">
                        @else
                            <div class="detail-placeholder">SP</div>
                        @endif
                        <div>
                            <h3>{{ $item->product_name }}</h3>
                            <p>Màu / Size: <strong>{{ str_replace(' - ', ' / ', $item->variant_name) }}</strong></p>
                            <p>Số lượng: <strong>{{ $item->quantity }}</strong></p>
                        </div>
                        <div class="product-price">
                            <span>{{ number_format($item->price, 0, ',', '.') }} đ</span>
                            <strong>{{ number_format($item->price * $item->quantity, 0, ',', '.') }} đ</strong>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="detail-card receiver-card">
                <div class="card-title"><h2>Thông tin người nhận</h2></div>
                <div class="receiver-grid">
                    <div><span>Người nhận</span><strong>{{ $order->recipient_name }}</strong></div>
                    <div><span>Số điện thoại</span><strong><a href="tel:{{ $order->phone }}">{{ $order->phone }}</a></strong></div>
                    <div class="full"><span>Địa chỉ giao hàng</span><strong>{{ $order->address }}</strong></div>
                </div>
            </section>
        </main>

        <aside>
            <section class="detail-card cost-card">
                <div class="card-title"><h2>Chi tiết thanh toán</h2></div>
                <dl>
                    <div><dt>Tạm tính</dt><dd>{{ number_format($subtotal, 0, ',', '.') }} đ</dd></div>
                    <div><dt>Phí vận chuyển</dt><dd>{{ $shippingFee > 0 ? number_format($shippingFee, 0, ',', '.').' đ' : 'Miễn phí' }}</dd></div>
                    <div class="grand-total"><dt>Tổng thanh toán</dt><dd>{{ number_format($order->total, 0, ',', '.') }} đ</dd></div>
                </dl>
                <div class="payment-method">
                    <span>Phương thức thanh toán</span>
                    <strong>{{ $order->payment_label }}</strong>
                    <small class="payment-{{ $order->payment_status }}">{{ $order->payment_status_label }}</small>
                </div>
            </section>

            <section class="detail-actions">
                @if(in_array($order->status, ['pending', 'processing', 'shipping'], true))
                    <button class="detail-button danger" type="button" data-open-cancel-modal data-cancel-action="{{ route('user.orders.cancel', $order) }}">Hủy đơn hàng</button>
                    @if($order->isMomoOrder() && $order->can_retry_momo_payment)
                        <form method="POST" action="{{ route('user.orders.momo.retry', $order) }}">
                            @csrf
                            <button class="detail-button primary" type="submit">Thanh toán lại với MoMo</button>
                        </form>
                    @endif
                @else
                    <form method="POST" action="{{ route('user.orders.reorder', $order) }}">
                        @csrf
                        <button class="detail-button primary" type="submit">Mua lại đơn này</button>
                    </form>
                @endif
                <a class="detail-button light" href="{{ route('shop.home') }}">Tiếp tục mua sắm</a>
            </section>
        </aside>
    </div>
</div>

@include('orders.partials.cancel-modal')

<style>
    .order-detail-page{--navy:#1e293b;--gold:#b4860b;--muted:#64748b;--line:#e2e8f0;color:#334155}
    .detail-topbar{display:flex;align-items:center;gap:16px;margin-bottom:18px;color:#64748b;font-size:13px}.detail-topbar>a{margin-right:auto;color:#475569;text-decoration:none;font-weight:700}.detail-topbar>a:hover{color:var(--gold)}.detail-topbar>span:nth-child(2){padding-right:16px;border-right:1px solid var(--line);color:var(--navy);font-weight:800}
    .detail-status{padding:6px 10px;border-radius:999px;font-weight:800}.status-pending{background:#fef3c7;color:#92400e}.status-processing{background:#dbeafe;color:#1d4ed8}.status-shipping{background:#e0e7ff;color:#4338ca}.status-completed{background:#dcfce7;color:#166534}.status-cancelled{background:#fee2e2;color:#991b1b}
    .tracking-card,.cancelled-banner,.detail-card{background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 2px 7px rgba(15,23,42,.05)}
    .tracking-card{padding:20px 24px;margin-bottom:18px}.section-heading{display:flex;justify-content:space-between;align-items:flex-start}.section-heading span{color:var(--gold);font-size:11px;font-weight:800;letter-spacing:.1em}.section-heading h1{margin:5px 0 0;color:var(--navy);font-size:20px}.section-heading small{color:var(--muted)}
    .tracking-steps{display:grid;grid-template-columns:repeat(4,1fr);margin-top:28px}.tracking-step{position:relative;display:grid;justify-items:center;gap:6px;text-align:center;color:#94a3b8}.tracking-step:not(:first-child):before{content:"";position:absolute;top:16px;right:50%;width:100%;height:3px;background:#e2e8f0;z-index:0}.tracking-step.done:not(:first-child):before{background:#d4a72c}.step-dot{position:relative;z-index:1;display:grid;place-items:center;width:34px;height:34px;border:3px solid #e2e8f0;border-radius:50%;background:#fff;font-weight:800}.tracking-step.done .step-dot{border-color:#d4a72c;background:#d4a72c;color:#fff}.tracking-step.current .step-dot{box-shadow:0 0 0 5px #fef3c7}.tracking-step strong{color:#64748b;font-size:13px}.tracking-step.done strong{color:var(--navy)}.tracking-step span{font-size:11px}
    .cancelled-banner{display:flex;align-items:center;gap:16px;padding:20px 24px;margin-bottom:18px;background:#fff7f7;border-color:#fecaca}.cancel-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:50%;background:#fee2e2;color:#dc2626;font-size:28px}.cancelled-banner h1{margin:0 0 5px;color:#991b1b;font-size:20px}.cancelled-banner p{margin:0;color:#7f1d1d;font-size:13px}.cancelled-banner strong{display:block;margin-top:8px;color:#991b1b;font-size:13px}.detail-error{padding:12px 15px;margin-bottom:14px;background:#fee2e2;color:#991b1b;border-radius:7px}
    .detail-grid{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(280px,.8fr);align-items:start;gap:18px}.detail-grid main{display:grid;gap:18px}.detail-card{padding:18px}.card-title{display:flex;justify-content:space-between;align-items:center;padding-bottom:13px;border-bottom:1px solid var(--line)}.card-title h2{margin:0;color:var(--navy);font-size:17px}.card-title span{color:var(--muted);font-size:12px}
    .detail-product{display:grid;grid-template-columns:82px minmax(0,1fr) auto;align-items:center;gap:15px;padding:16px 0;border-bottom:1px solid #f1f5f9}.detail-product:last-child{padding-bottom:0;border-bottom:0}.detail-product img,.detail-placeholder{width:82px;height:82px;object-fit:cover;border:1px solid var(--line);border-radius:7px;background:#f1f5f9}.detail-placeholder{display:grid;place-items:center;color:#94a3b8;font-weight:800}.detail-product h3{margin:0 0 8px;color:var(--navy);font-size:14px;line-height:1.35}.detail-product p{margin:4px 0;color:var(--muted);font-size:12px}.product-price{display:grid;justify-items:end;gap:7px;white-space:nowrap}.product-price span{color:var(--muted);font-size:12px}.product-price strong{color:#ee4d2d;font-size:15px}
    .receiver-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;padding-top:16px}.receiver-grid div{display:grid;gap:5px}.receiver-grid .full{grid-column:1/-1}.receiver-grid span,.payment-method span{color:var(--muted);font-size:12px}.receiver-grid strong{color:#334155;font-size:14px;line-height:1.5}.receiver-grid a{color:#2563eb;text-decoration:none}
    .cost-card dl{margin:7px 0 0}.cost-card dl>div{display:flex;justify-content:space-between;gap:15px;padding:10px 0;color:#64748b;font-size:13px}.cost-card dt,.cost-card dd{margin:0}.cost-card dd{color:#334155;font-weight:700}.cost-card .grand-total{align-items:center;margin-top:5px;padding-top:15px;border-top:1px solid var(--line);color:var(--navy);font-weight:800}.cost-card .grand-total dd{color:#ee4d2d;font-size:20px}.payment-method{display:grid;gap:5px;margin-top:12px;padding:13px;background:#f8fafc;border-radius:7px}.payment-method strong{font-size:13px}.payment-method small{font-weight:700}.payment-paid{color:#15803d}.payment-unpaid{color:#b45309}
    .detail-actions{display:grid;gap:9px;margin-top:14px}.detail-actions form{margin:0}.detail-button{display:flex;align-items:center;justify-content:center;width:100%;min-height:42px;padding:10px;border:1px solid transparent;border-radius:7px;text-decoration:none;font-weight:800;cursor:pointer}.detail-button.primary{background:var(--navy);color:#fff}.detail-button.danger{background:#fff;color:#dc2626;border-color:#fecaca}.detail-button.light{background:#fff;color:#475569;border-color:#cbd5e1}
    @media(max-width:850px){.detail-grid{grid-template-columns:1fr}.detail-grid aside{display:grid;grid-template-columns:1fr 1fr;gap:15px}.detail-actions{margin-top:0}.tracking-card{overflow-x:auto}.tracking-steps{min-width:620px}}
    @media(max-width:600px){.detail-topbar>span:nth-child(2){display:none}.section-heading small{display:none}.tracking-card{padding:18px}.detail-grid aside{grid-template-columns:1fr}.detail-product{grid-template-columns:64px minmax(0,1fr)}.detail-product img,.detail-placeholder{width:64px;height:64px}.product-price{grid-column:2;justify-items:start;grid-auto-flow:column}.receiver-grid{grid-template-columns:1fr}.receiver-grid .full{grid-column:auto}}
</style>
@endsection
