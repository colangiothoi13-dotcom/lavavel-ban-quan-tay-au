@extends('layouts.app')

@section('content')
@php
    $title = match ($status ?? '') {
        'success' => 'Thanh toán MoMo thành công',
        'paid_refund_pending' => 'Thanh toán thành công, cần hoàn tiền thừa',
        'refund_pending' => 'Thanh toán cần xử lý hoàn tiền',
        'refunded' => 'Giao dịch MoMo đã hoàn tiền',
        'cancelled' => 'Thanh toán MoMo đã hủy',
        'failed' => 'Thanh toán MoMo thất bại',
        default => 'Đang chờ xác nhận MoMo',
    };
    $class = match ($status ?? '') {
        'success' => 'success',
        'paid_refund_pending' => 'warning',
        'refund_pending' => 'warning',
        'cancelled' => 'danger',
        'failed' => 'danger',
        default => 'warning',
    };
@endphp

<div class="momo-result {{ $class }}">
    <h1>{{ $title }}</h1>
    <p>{{ $message ?? 'Đơn hàng của bạn đang chờ xác nhận từ MoMo.' }}</p>

    <div class="momo-result-card">
        <h2>Đơn hàng #{{ $order->id }}</h2>
        <p>Phương thức: <strong>{{ $order->payment_label }}</strong></p>
        <p>Trạng thái thanh toán: <strong>{{ $order->payment_status_label }}</strong></p>
        <p>Tổng tiền: <strong>{{ number_format($order->total, 0, ',', '.') }} đ</strong></p>
    </div>

    <div class="momo-result-actions">
        <a class="button button-light" href="{{ route('user.orders.show', $order) }}">Xem chi tiết đơn hàng</a>
        <a class="button" href="{{ route('user.orders.index') }}">Quay lại đơn mua</a>
    </div>

    @if($order->can_retry_momo_payment)
        <form method="POST" action="{{ route('user.orders.momo.retry', $order) }}">
            @csrf
            <button class="button button-primary" type="submit">Thanh toán lại với MoMo</button>
        </form>
    @endif
</div>

<style>
    .momo-result{background:#fff;padding:24px;border:1px solid #e2e8f0;border-radius:10px;display:grid;gap:12px}
    .momo-result h1{margin:0;color:#1e293b;font-size:24px}
    .momo-result .momo-result-card{background:#f8fafc;padding:16px;border-radius:8px}
    .momo-result.success{border-color:#86efac}
    .momo-result.warning{border-color:#fde68a}
    .momo-result.danger{border-color:#fecaca}
    .momo-result-actions{display:flex;gap:10px;flex-wrap:wrap}
    .button{display:inline-flex;padding:10px 14px;border-radius:6px;border:0;background:#334155;color:#fff;text-decoration:none;font-weight:700}
    .button-light{background:#fff;border:1px solid #cbd5e1;color:#334155}
</style>
@endsection
