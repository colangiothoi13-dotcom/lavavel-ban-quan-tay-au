<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phiếu đóng hàng</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font: 13px/1.45 Arial, sans-serif; background: #eef2f7; }
        .print-toolbar { display: flex; justify-content: space-between; max-width: 186mm; margin: 16px auto; }
        .print-toolbar a, .print-toolbar button { padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; color: #334155; text-decoration: none; cursor: pointer; }
        .packing-slip { width: 186mm; min-height: 273mm; margin: 0 auto 16px; padding: 12mm; background: #fff; page-break-after: always; }
        .packing-slip:last-child { page-break-after: auto; }
        .slip-header { display: flex; justify-content: space-between; gap: 20px; padding-bottom: 14px; border-bottom: 2px solid #172033; }
        .slip-header h1 { margin: 0 0 4px; font-size: 22px; letter-spacing: .4px; }
        .slip-header p { margin: 0; color: #64748b; }
        .order-number { text-align: right; }
        .order-number strong { display: block; color: #0f766e; font-size: 18px; }
        .order-number span { color: #64748b; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin: 18px 0; }
        .info-block h2 { margin: 0 0 6px; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; }
        .info-block p { margin: 2px 0; }
        .items { width: 100%; border-collapse: collapse; }
        .items th, .items td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .items th { background: #f8fafc; color: #475569; font-size: 11px; }
        .items .number { text-align: right; white-space: nowrap; }
        .item-variant { display: block; margin-top: 2px; color: #64748b; font-size: 11px; }
        .totals { width: 260px; margin: 18px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; gap: 20px; padding: 4px 0; }
        .totals .grand-total { margin-top: 5px; padding-top: 8px; border-top: 2px solid #172033; font-size: 16px; font-weight: 700; }
        .slip-footer { display: flex; justify-content: space-between; gap: 20px; margin-top: 28px; padding-top: 12px; border-top: 1px dashed #cbd5e1; color: #64748b; font-size: 11px; }
        @media print {
            body { background: #fff; }
            .print-toolbar { display: none; }
            .packing-slip { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <a href="{{ route('admin.orders.index') }}">← Quay lại đơn hàng</a>
        <button type="button" onclick="window.print()">In lại</button>
    </div>

    @foreach($orders as $order)
        @php
            $subtotal = (float) $order->items->sum(fn ($item) => (float) $item->price * $item->quantity);
            $shippingFee = (float) ($order->shipping_fee ?? max(0, (float) $order->total - $subtotal));
            $shippingProvider = $order->shipping_provider ?: ($order->ghn_order_code ? 'ghn' : null);
        @endphp
        <article class="packing-slip">
            <header class="slip-header">
                <div>
                    <h1>PHIẾU ĐÓNG HÀNG</h1>
                    <p>Bán quần Tây Âu</p>
                </div>
                <div class="order-number">
                    <strong>DH{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</strong>
                    <span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </header>

            <section class="info-grid">
                <div class="info-block">
                    <h2>Thông tin nhận hàng</h2>
                    <p><strong>{{ $order->recipient_name }}</strong></p>
                    <p>{{ $order->phone }}</p>
                    <p>{{ $order->address }}</p>
                </div>
                <div class="info-block">
                    <h2>Thanh toán & vận chuyển</h2>
                    <p>Thanh toán: {{ $order->payment_label }}</p>
                    <p>Trạng thái: {{ $order->payment_status_label }}</p>
                    <p>Đơn vị: {{ $shippingProvider === 'ghn' ? 'GHN' : 'Chưa gán' }}</p>
                    @if($order->ghn_order_code)<p>Mã vận đơn: <strong>{{ $order->ghn_order_code }}</strong></p>@endif
                </div>
            </section>

            <table class="items">
                <thead>
                    <tr><th>Sản phẩm</th><th class="number">SL</th><th class="number">Đơn giá</th><th class="number">Thành tiền</th></tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}<span class="item-variant">{{ $item->variant_name }}</span></td>
                            <td class="number">{{ $item->quantity }}</td>
                            <td class="number">{{ number_format($item->price, 0, ',', '.') }} đ</td>
                            <td class="number">{{ number_format($item->price * $item->quantity, 0, ',', '.') }} đ</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Chưa có sản phẩm</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="totals">
                <div><span>Tạm tính</span><strong>{{ number_format($subtotal, 0, ',', '.') }} đ</strong></div>
                <div><span>Phí vận chuyển</span><strong>{{ number_format($shippingFee, 0, ',', '.') }} đ</strong></div>
                <div class="grand-total"><span>Tổng cộng</span><strong>{{ number_format($order->total, 0, ',', '.') }} đ</strong></div>
            </div>

            <footer class="slip-footer">
                <span>Vui lòng kiểm tra sản phẩm trước khi đóng gói.</span>
                <span>Người đóng hàng: __________________</span>
            </footer>
        </article>
    @endforeach

    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>
