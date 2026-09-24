@extends('layouts.app')

@section('content')
<div class="report-page">
    <div class="report-heading">
        <div><h1>Thống kê doanh thu</h1><p>Thống kê các đơn đã thanh toán và tự động loại đơn đã hủy.</p></div>
        <a class="report-export" href="{{ route('admin.reports.export', compact('from', 'to')) }}">Xuất file Excel</a>
    </div>

    <form class="report-filter" method="GET" action="{{ route('admin.reports.index') }}">
        <label>Từ ngày <input type="date" name="from" value="{{ $from }}" required></label>
        <label>Đến ngày <input type="date" name="to" value="{{ $to }}" required></label>
        <button type="submit">Thống kê</button>
    </form>
    @error('to') <div class="report-error">{{ $message }}</div> @enderror

    <div class="report-summary">
        <article><span>Tổng doanh thu</span><strong>{{ number_format($totalRevenue, 0, ',', '.') }} đ</strong></article>
        <article><span>Đơn đã thanh toán</span><strong>{{ number_format($totalOrders) }}</strong></article>
        <article><span>Sản phẩm đã bán</span><strong>{{ number_format($totalProducts) }}</strong></article>
    </div>

    <section class="report-chart-card">
        <div class="chart-title"><h2>Doanh thu theo ngày</h2><span>Đơn vị: VNĐ</span></div>
        <div class="chart-wrap">
            @if($maxDailyRevenue > 0)
                <div class="bar-chart" role="img" aria-label="Biểu đồ cột doanh thu theo ngày">
                    @foreach($dailyRevenue as $point)
                        @php
                            $barHeight = $point['total'] > 0 ? max(3, ($point['total'] / $maxDailyRevenue) * 100) : 0;
                        @endphp
                        <div class="bar-column" title="{{ $point['label'] }}: {{ number_format($point['total'], 0, ',', '.') }} đ">
                            <div class="bar-value">{{ $point['total'] > 0 ? number_format($point['total'], 0, ',', '.') : '' }}</div>
                            <div class="bar" style="height: {{ $barHeight }}%"></div>
                            <div class="bar-label">{{ $point['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="chart-empty">Chưa có đơn đã thanh toán trong khoảng thời gian này.</div>
            @endif
        </div>
    </section>

    @php
        $categoryStops = [];
        $categoryOffset = 0;
        foreach ($categoryRevenue as $index => $category) {
            $categoryEnd = $index === $categoryRevenue->count() - 1
                ? 100
                : min(100, $categoryOffset + $category['percentage']);
            $categoryStops[] = $category['color'].' '.$categoryOffset.'% '.$categoryEnd.'%';
            $categoryOffset = $categoryEnd;
        }
        $categoryGradient = $categoryStops !== []
            ? 'conic-gradient('.implode(', ', $categoryStops).')'
            : 'conic-gradient(#e2e8f0 0 100%)';

        $paymentStops = [];
        $paymentOffset = 0;
        foreach ($paymentBreakdown as $index => $payment) {
            $paymentEnd = $index === $paymentBreakdown->count() - 1
                ? 100
                : min(100, $paymentOffset + $payment['percentage']);
            $paymentStops[] = $payment['color'].' '.$paymentOffset.'% '.$paymentEnd.'%';
            $paymentOffset = $paymentEnd;
        }
        $paymentGradient = $paymentStops !== []
            ? 'conic-gradient('.implode(', ', $paymentStops).')'
            : 'conic-gradient(#e2e8f0 0 100%)';
    @endphp

    <div class="report-visual-grid">
        <section class="report-chart-card pie-report-card">
            <div class="chart-title"><div><h2>Tỷ trọng doanh thu theo danh mục</h2><p class="chart-note">Tính trên doanh thu sản phẩm đã thanh toán, chưa gồm phí vận chuyển.</p></div><span>VNĐ</span></div>
            <div class="pie-layout">
                <div class="donut-chart" style="--donut-background: {{ $categoryGradient }}" role="img" aria-label="Biểu đồ tròn tỷ trọng doanh thu theo danh mục">
                    @if($categoryRevenue->isNotEmpty())<strong>{{ number_format($categoryRevenue->sum('total'), 0, ',', '.') }} đ</strong><small>Doanh thu SP</small>@else<span>Chưa có dữ liệu</span>@endif
                </div>
                <div class="pie-legend">
                    @forelse($categoryRevenue as $category)
                        <div class="pie-legend-row"><span class="legend-label"><i style="--legend-color: {{ $category['color'] }}"></i>{{ $category['label'] }}</span><strong>{{ number_format($category['percentage'], 2, ',', '.') }}%</strong><small>{{ number_format($category['total'], 0, ',', '.') }} đ</small></div>
                    @empty
                        <div class="chart-empty-inline">Chưa có dữ liệu danh mục trong kỳ.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="report-chart-card pie-report-card">
            <div class="chart-title"><div><h2>Phân bổ theo phương thức thanh toán</h2><p class="chart-note">Tỷ trọng trên tổng giá trị các đơn đã thanh toán.</p></div><span>VNĐ</span></div>
            <div class="pie-layout">
                <div class="donut-chart" style="--donut-background: {{ $paymentGradient }}" role="img" aria-label="Biểu đồ tròn phân bổ phương thức thanh toán">
                    @if($paymentBreakdown->isNotEmpty())<strong>{{ number_format($paymentBreakdown->sum('total'), 0, ',', '.') }} đ</strong><small>Tổng thu</small>@else<span>Chưa có dữ liệu</span>@endif
                </div>
                <div class="pie-legend">
                    @forelse($paymentBreakdown as $payment)
                        <div class="pie-legend-row"><span class="legend-label"><i style="--legend-color: {{ $payment['color'] }}"></i>{{ $payment['label'] }}</span><strong>{{ number_format($payment['percentage'], 2, ',', '.') }}%</strong><small>{{ number_format($payment['count']) }} đơn · {{ number_format($payment['total'], 0, ',', '.') }} đ</small></div>
                    @empty
                        <div class="chart-empty-inline">Chưa có dữ liệu thanh toán trong kỳ.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <section class="report-table-card">
        <h2>Chi tiết đơn hàng đã thanh toán</h2>
        <div class="table-scroll"><table>
            <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Khách hàng</th><th>Số điện thoại</th><th>Số lượng</th><th>Thanh toán</th><th>Doanh thu</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr><td>#{{ $order->id }}</td><td>{{ $order->created_at->format('d/m/Y H:i') }}</td><td>{{ $order->recipient_name }}</td><td><a href="tel:{{ $order->phone }}">{{ $order->phone }}</a></td><td>{{ $order->items->sum('quantity') }}</td><td>{{ $order->payment_label }}</td><td><strong>{{ number_format($order->total, 0, ',', '.') }} đ</strong></td></tr>
                @empty
                    <tr><td colspan="7" class="table-empty">Không có dữ liệu phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="report-table-card">
        <h2>Sản phẩm đã bán và trừ khỏi kho</h2>
        <div class="table-scroll"><table>
            <thead><tr><th>Sản phẩm</th><th>Biến thể</th><th>Đã bán</th><th>Tồn kho hiện tại</th></tr></thead>
            <tbody>
                @forelse($soldProducts as $product)
                    <tr><td>{{ $product['product'] }}</td><td>{{ $product['variant'] }}</td><td><strong>{{ number_format($product['quantity']) }}</strong></td><td>{{ number_format($product['stock']) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="table-empty">Chưa có sản phẩm từ đơn đã thanh toán.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>

<style>
.report-page{color:#1e293b}.report-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px}.report-heading h1{font-size:26px;margin:0 0 6px}.report-heading p{color:#64748b;margin:0}.report-export,.report-filter button{border:0;border-radius:6px;background:#f4511e;color:#fff;text-decoration:none;font-weight:700;padding:12px 18px;cursor:pointer}.report-export:hover,.report-filter button:hover{background:#d93d0e}.report-filter{display:flex;align-items:end;gap:16px;padding:18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px}.report-filter label{display:grid;gap:7px;font-size:14px;font-weight:700}.report-filter input{min-width:190px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:5px;background:#fff;font:inherit}.report-error{color:#b91c1c;background:#fee2e2;padding:10px 14px;border-radius:5px;margin-bottom:16px}.report-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:20px}.report-summary article{padding:20px;border:1px solid #e2e8f0;border-left:4px solid #f4511e;border-radius:8px;background:#fff}.report-summary span{display:block;color:#64748b;font-size:14px;margin-bottom:10px}.report-summary strong{font-size:24px}.report-chart-card,.report-table-card{border:1px solid #e2e8f0;border-radius:8px;background:#fff;padding:20px;margin-bottom:20px}.chart-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}.chart-title h2,.report-table-card h2{font-size:18px;margin:0}.chart-title span{font-size:13px;color:#64748b}.chart-wrap{position:relative;height:360px}.chart-wrap canvas{width:100%;height:100%}.chart-empty{position:absolute;inset:0;display:grid;place-items:center;color:#64748b;background:#f8fafc}.table-scroll{overflow-x:auto}.report-table-card h2{margin-bottom:14px}.report-table-card table{width:100%;border-collapse:collapse;min-width:720px}.report-table-card th,.report-table-card td{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0}.report-table-card th{background:#f8fafc;color:#475569;font-size:13px}.report-table-card td{font-size:14px}.report-table-card td:last-child{color:#e64a19}.table-empty{text-align:center!important;color:#64748b;padding:28px!important}@media(max-width:800px){.report-heading{align-items:flex-start;flex-direction:column}.report-filter{align-items:stretch;flex-direction:column}.report-filter input{width:100%;min-width:0}.report-summary{grid-template-columns:1fr}.chart-wrap{height:300px}}
</style>
<style>
.chart-wrap{background:repeating-linear-gradient(to bottom,#f8fafc 0,#f8fafc 1px,transparent 1px,transparent 20%);border-bottom:1px solid #cbd5e1;padding:20px 12px 0}
.bar-chart{height:100%;display:flex;align-items:end;gap:clamp(3px,1vw,12px);overflow-x:auto;padding:0 8px}
.bar-column{height:100%;min-width:28px;flex:1;display:grid;grid-template-rows:24px 1fr 28px;align-items:end;text-align:center}
.bar{width:min(48px,100%);height:0;justify-self:center;background:#4285e8;border-radius:4px 4px 0 0}
.bar-value,.bar-label{font-size:11px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar-label{color:#64748b;padding-top:8px}
</style>

<style>
.report-visual-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-bottom:20px}.pie-report-card{margin-bottom:0}.pie-report-card .chart-title{align-items:flex-start}.chart-note{margin:4px 0 0;color:#64748b;font-size:12px;font-weight:400}.pie-layout{display:grid;grid-template-columns:minmax(170px,.85fr) minmax(0,1.15fr);align-items:center;gap:20px;min-height:250px}.donut-chart{display:grid;place-content:center;justify-items:center;aspect-ratio:1;width:min(220px,100%);margin:auto;border-radius:50%;background:var(--donut-background);position:relative}.donut-chart::after{content:"";position:absolute;inset:24%;border-radius:50%;background:#fff}.donut-chart strong,.donut-chart small,.donut-chart>span{position:relative;z-index:1;text-align:center}.donut-chart strong{font-size:16px;color:#1e293b}.donut-chart small{margin-top:4px;color:#64748b;font-size:11px}.donut-chart>span{z-index:2;color:#64748b;font-size:11px}.pie-legend{display:grid;gap:7px}.pie-legend-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:3px 9px;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12px}.pie-legend-row:last-child{border-bottom:0}.pie-legend-row strong{font-size:12px;color:#334155}.pie-legend-row small{grid-column:1/-1;color:#64748b;font-size:10px}.legend-label{display:flex;align-items:center;gap:7px;min-width:0;overflow:hidden;color:#475569;white-space:nowrap;text-overflow:ellipsis}.legend-label i{display:inline-block;width:10px;height:10px;flex:0 0 10px;border-radius:50%;background:var(--legend-color)}.chart-empty-inline{padding:20px 0;color:#64748b;font-size:12px}@media(max-width:1050px){.report-visual-grid{grid-template-columns:1fr}}@media(max-width:600px){.pie-layout{grid-template-columns:1fr}.donut-chart{width:190px}.pie-legend{width:100%}}
</style>

@endsection
