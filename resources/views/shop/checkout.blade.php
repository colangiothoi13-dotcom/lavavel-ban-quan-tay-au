@extends('layouts.shop', ['title' => 'Thanh toán'])
@section('content')
<h1>Thông tin thanh toán</h1>
<p>Kiểm tra thông tin nhận hàng rồi xác nhận đặt hàng.</p>
<form method="POST" action="{{ route('checkout.place') }}">
	@csrf
	@foreach(request()->input('selected_items', []) as $selectedId)
		<input type="hidden" name="selected_items[]" value="{{ $selectedId }}">
	@endforeach
	<div class="field"><label>Họ và tên</label><input name="name" value="{{ old('name', auth()->user()?->name) }}" required></div>
	<div class="field"><label>Số điện thoại</label><input name="phone" value="{{ old('phone', auth()->user()?->phone) }}" required></div>
	<div class="field"><label>Địa chỉ nhận hàng</label><textarea name="address" rows="4" required>{{ old('address') }}</textarea></div>
	<h3>Tổng đơn hàng: {{ number_format($items->sum('total')) }} đ</h3>
	<button class="btn" type="submit">Xác nhận đặt hàng</button>
</form>
@endsection
