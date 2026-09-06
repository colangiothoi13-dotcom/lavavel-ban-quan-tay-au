@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@php
    $subtotal = (int) $items->sum('total');
    $quantity = (int) $items->sum('quantity');
    $defaultAddress = $addresses->firstWhere('is_default', true) ?? $addresses->first();
@endphp

<div class="checkout-page">
    <h1>Thông tin thanh toán</h1>
    <p class="checkout-lead">Chọn địa chỉ để GHN tính phí vận chuyển chính xác.</p>
    @if($errors->any())<div class="checkout-error">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('checkout.place') }}" id="checkout-form">
        @csrf
        @foreach(request()->input('selected_items', []) as $selectedId)
            <input type="hidden" name="selected_items[]" value="{{ $selectedId }}">
        @endforeach
        <input type="hidden" name="name" id="checkout-name">
        <input type="hidden" name="phone" id="checkout-phone">
        <input type="hidden" name="address" id="checkout-address" value="{{ old('address', $defaultAddress?->full_address) }}">
        <input type="hidden" name="ghn_district_id" id="checkout-district-id">
        <input type="hidden" name="ghn_ward_code" id="checkout-ward-code">

        <section class="checkout-section">
            <label for="saved-address">Địa chỉ nhận hàng</label>
            @if($addresses->isNotEmpty())
                <select name="saved_address_id" id="saved-address">
                    @foreach($addresses as $savedAddress)
                        <option value="{{ $savedAddress->id }}"
                            data-address="{{ $savedAddress->full_address }}"
                            data-district-id="{{ $savedAddress->ghn_district_id }}"
                            data-ward-code="{{ $savedAddress->ghn_ward_code }}"
                            @selected(old('saved_address_id', $defaultAddress?->id) == $savedAddress->id)>
                            {{ $savedAddress->recipient_name }} - {{ $savedAddress->full_address }}{{ $savedAddress->is_default ? ' (Mặc định)' : '' }}
                        </option>
                    @endforeach
                    <option value="">— Dùng địa chỉ mới —</option>
                </select>
            @else
                <input type="hidden" name="saved_address_id" id="saved-address" value="">
            @endif
            <button type="button" class="address-trigger" id="address-trigger">+ Thêm địa chỉ khác</button>
            <p class="shipping-status" id="shipping-status">Đang kiểm tra địa chỉ với GHN...</p>
        </section>

        <section class="checkout-section">
            <h2 class="product-section-title">Sản phẩm đặt mua</h2>
            <div class="checkout-products">
                @foreach($items as $item)
                    @php
                        $checkoutImage = $item['variant']->image ?: ($item['variant']->product->image ?? '');
                        if ($checkoutImage && !str_starts_with($checkoutImage, 'http')) {
                            $checkoutImage = asset('storage/'.$checkoutImage);
                        }
                    @endphp
                    <article class="checkout-product">
                        <div class="checkout-product-image">
                            @if($checkoutImage)
                                <img src="{{ $checkoutImage }}" alt="{{ $item['variant']->product->name }}" loading="lazy">
                            @else
                                <div class="product-image-empty" aria-label="Sản phẩm chưa có ảnh">Chưa có ảnh</div>
                            @endif
                        </div>
                        <div class="checkout-product-info">
                            <h3>{{ $item['variant']->product->name }}</h3>
                            <p>Màu: {{ $item['variant']->color ?: '—' }} · Kích thước: {{ $item['variant']->size ?: '—' }}</p>
                            <p>{{ number_format($item['price'], 0, ',', '.') }} đ × {{ $item['quantity'] }}</p>
                        </div>
                        <strong class="checkout-product-total">{{ number_format($item['total'], 0, ',', '.') }} đ</strong>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="checkout-section">
            <label>Phương thức thanh toán</label>
            <div class="payment-options">
                <label><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash') required> Tiền mặt khi nhận hàng</label>
                <label><input type="radio" name="payment_method" value="bank_transfer" @checked(old('payment_method') === 'bank_transfer')> Chuyển khoản ngân hàng</label>
                <label><input type="radio" name="payment_method" value="momo" @checked(old('payment_method') === 'momo')> MoMo</label>
            </div>
        </section>

        <section class="order-summary">
            <div><span>Tạm tính ({{ $quantity }} sản phẩm)</span><strong>{{ number_format($subtotal, 0, ',', '.') }} đ</strong></div>
            <div><span>Phí vận chuyển GHN</span><strong id="shipping-fee">—</strong></div>
            <div class="grand-total"><span>Tổng tiền phải trả</span><strong id="grand-total">{{ number_format($subtotal, 0, ',', '.') }} đ</strong></div>
            <button type="submit" id="place-order" disabled>Xác nhận đặt hàng</button>
        </section>
    </form>
</div>

<div class="address-modal" id="address-modal" role="dialog" aria-modal="true" aria-labelledby="address-title">
    <div class="address-dialog">
        <button type="button" class="address-close" id="address-close" aria-label="Đóng">&times;</button>
        <h2 id="address-title">Địa chỉ mới</h2>
        <p>Địa giới hành chính và phí vận chuyển được lấy trực tiếp từ GHN.</p>
        <div class="address-grid">
            <div class="address-field"><label for="recipient-name">Họ và tên</label><input id="recipient-name" value="{{ old('name', auth()->user()?->name) }}" autocomplete="name" required></div>
            <div class="address-field"><label for="recipient-phone">Số điện thoại</label><input id="recipient-phone" type="tel" value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" required></div>
            <div class="address-field"><label for="province">Tỉnh/Thành phố</label><select id="province" required><option value="">Đang tải từ GHN...</option></select></div>
            <div class="address-field"><label for="district">Quận/Huyện</label><select id="district" disabled required><option value="">Chọn Tỉnh/Thành phố trước</option></select></div>
            <div class="address-field full"><label for="ward">Phường/Xã</label><select id="ward" disabled required><option value="">Chọn Quận/Huyện trước</option></select></div>
            <div class="address-field full address-search-field">
                <label for="street-address">Địa chỉ cụ thể</label>
                <input id="street-address" placeholder="Chọn Phường/Xã trước" autocomplete="street-address" disabled required>
                <p class="field-hint" id="address-hint">Chọn Phường/Xã rồi nhập số nhà, tên đường hoặc địa điểm.</p>
            </div>
            <div class="map-wrap"><div id="address-map" aria-label="Bản đồ khu vực giao hàng"></div><div class="map-placeholder" id="map-placeholder">Chọn Phường/Xã để hiển thị khu vực trên bản đồ.</div></div>
        </div>
        <div class="address-actions"><button type="button" class="address-cancel" id="address-cancel">Trở lại</button><button type="button" class="address-submit" id="address-submit">Hoàn thành</button></div>
    </div>
</div>

<style>
.checkout-page{color:#334155}.checkout-page>h1{margin:0 0 6px;color:#1e293b;font-size:24px}.checkout-lead{margin:0 0 20px;color:#64748b}.checkout-error{margin-bottom:16px;padding:12px 14px;border-radius:6px;background:#fee2e2;color:#991b1b}.checkout-section{margin-bottom:22px}.checkout-section>label{display:block;margin-bottom:8px;font-weight:700}.checkout-section>select{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:6px;background:#fff}.product-section-title{margin:0 0 10px;font-size:17px}.checkout-products{overflow:hidden;border:1px solid #e2e8f0;border-radius:8px}.checkout-product{display:grid;grid-template-columns:82px minmax(0,1fr) auto;align-items:center;gap:14px;padding:14px;background:#fff}.checkout-product+.checkout-product{border-top:1px solid #e2e8f0}.checkout-product-image{width:82px;height:82px;overflow:hidden;border-radius:6px;background:#f1f5f9}.checkout-product-image img{width:100%;height:100%;object-fit:cover}.product-image-empty{display:grid;width:100%;height:100%;place-items:center;color:#94a3b8;font-size:11px;text-align:center}.checkout-product-info h3{margin:0 0 7px;color:#1e293b;font-size:15px}.checkout-product-info p{margin:3px 0;color:#64748b;font-size:13px}.checkout-product-total{color:#ea580c;white-space:nowrap}.address-trigger{width:100%;margin-top:10px;padding:13px;border:1px dashed #f45135;border-radius:6px;background:#fff7ed;color:#c2410c;font-weight:700;cursor:pointer}.shipping-status{margin:8px 0 0;color:#64748b;font-size:13px}.shipping-status.error{color:#dc2626}.shipping-status.success{color:#15803d}.payment-options{display:flex;flex-wrap:wrap;gap:12px}.payment-options label{padding:12px 16px;border:1px solid #cbd5e1;border-radius:6px}.order-summary{margin-top:10px;padding:20px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.order-summary>div{display:flex;justify-content:space-between;gap:20px;padding:9px 0}.order-summary .grand-total{margin-top:8px;padding-top:16px;border-top:1px solid #cbd5e1;font-size:19px}.grand-total strong{color:#ea580c}.order-summary>button{display:block;width:100%;margin-top:18px;padding:13px;border:0;border-radius:6px;background:#ea580c;color:#fff;font-weight:800;cursor:pointer}.order-summary>button:disabled{background:#94a3b8;cursor:not-allowed}.address-modal{display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;padding:16px;background:#0f172a73}.address-modal.open{display:flex}.address-dialog{position:relative;width:min(760px,100%);max-height:92vh;overflow:auto;padding:28px;border-radius:8px;background:#fff;box-shadow:0 12px 35px #0f172a40}.address-dialog h2{margin:0 0 8px}.address-dialog>p{margin:0 0 20px;color:#64748b}.address-close{position:absolute;top:10px;right:14px;border:0;background:none;color:#64748b;font-size:28px;cursor:pointer}.address-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.address-field{position:relative}.address-field.full,.map-wrap{grid-column:1/-1}.address-field label{display:block;margin-bottom:6px;font-size:14px;font-weight:700}.address-field input,.address-field select{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:4px;background:#fff;font-size:15px}.address-field input:disabled,.address-field select:disabled{background:#f1f5f9;color:#94a3b8}.field-hint{min-height:18px;margin:5px 0 0;color:#64748b;font-size:12px}.field-hint.error{color:#dc2626}.map-wrap{position:relative;min-height:250px;overflow:hidden;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc}#address-map{height:250px}.map-placeholder{position:absolute;z-index:500;inset:0;display:grid;place-items:center;padding:24px;background:#f8fafc;color:#94a3b8;text-align:center}.map-placeholder[hidden]{display:none}.address-actions{display:flex;justify-content:flex-end;gap:14px;margin-top:22px}.address-actions button{padding:11px 24px;border-radius:5px;cursor:pointer}.address-cancel{border:0;background:#fff;color:#475569}.address-submit{border:0;background:#f45135;color:#fff;font-weight:800}@media(max-width:600px){.address-grid{grid-template-columns:1fr}.address-field.full,.map-wrap{grid-column:auto}.payment-options{display:grid}.address-dialog{padding:22px 18px}.checkout-product{grid-template-columns:68px minmax(0,1fr)}.checkout-product-image{width:68px;height:68px}.checkout-product-total{grid-column:2}}
</style>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(() => {
    const csrf = @json(csrf_token());
    const subtotal = {{ $subtotal }};
    const urls = {
        provinces: @json(route('ghn.provinces')),
        districts: @json(route('ghn.districts')),
        wards: @json(route('ghn.wards')),
        fee: @json(route('ghn.fee')),
        search: @json(route('user.addresses.search'))
    };
    const form = document.querySelector('#checkout-form');
    const saved = document.querySelector('#saved-address');
    const modal = document.querySelector('#address-modal');
    const province = document.querySelector('#province');
    const district = document.querySelector('#district');
    const ward = document.querySelector('#ward');
    const street = document.querySelector('#street-address');
    const hint = document.querySelector('#address-hint');
    const placeholder = document.querySelector('#map-placeholder');
    const submitOrder = document.querySelector('#place-order');
    const feeOutput = document.querySelector('#shipping-fee');
    const totalOutput = document.querySelector('#grand-total');
    const shippingStatus = document.querySelector('#shipping-status');
    const map = L.map('address-map').setView([16.047079,108.206230],5);
    let marker, accuracyCircle;
    let addressFeeTimer;
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
    const money = value => new Intl.NumberFormat('vi-VN').format(value) + ' đ';
    const setStatus = (text,type='') => {shippingStatus.textContent=text;shippingStatus.className='shipping-status '+type;};
    const setHint = (text,error=false) => {hint.textContent=text;hint.classList.toggle('error',error);};
    const fill = (select,items,label,valueKey,nameKey) => {select.replaceChildren(new Option(label,''));items.forEach(item=>select.add(new Option(item[nameKey],item[valueKey])));};
    const disableCheckout = message => {feeOutput.textContent='—';totalOutput.textContent=money(subtotal);submitOrder.disabled=true;if(message)setStatus(message,'error');};
    const showPosition=(lat,lng,zoom=16)=>{if(marker)marker.remove();if(accuracyCircle)accuracyCircle.remove();marker=L.marker([lat,lng],{draggable:true}).addTo(map);accuracyCircle=L.circle([lat,lng],{radius:10,color:'#f45135',fillColor:'#f45135',fillOpacity:.16,weight:2}).addTo(map);marker.on('drag',event=>accuracyCircle.setLatLng(event.target.getLatLng()));marker.on('dragend',event=>{const point=event.target.getLatLng();showPosition(point.lat,point.lng,18);setHint('Đã cập nhật vị trí ghim; vòng tròn thể hiện bán kính 10 m.');});map.setView([lat,lng],zoom);placeholder.hidden=true;};
    const findPosition=async queries=>{for(const q of queries){try{const response=await fetch(`${urls.search}?${new URLSearchParams({q})}`,{headers:{Accept:'application/json'}});if(!response.ok)continue;const result=((await response.json()).results||[])[0];if(result){showPosition(result.latitude,result.longitude);return true;}}catch{}}return false;};
    const locateWard=async()=>{placeholder.hidden=true;const wardName=ward.selectedOptions[0].text,districtName=district.selectedOptions[0].text,provinceName=province.selectedOptions[0].text;const found=await findPosition([`${wardName}, ${districtName}, ${provinceName}, Việt Nam`,`${districtName}, ${provinceName}, Việt Nam`,`${provinceName}, Việt Nam`]);if(!found){map.setView([16.047079,108.206230],5);setHint('Bản đồ đã mở; hãy bấm vào bản đồ để đặt ghim chính xác.');}setTimeout(()=>map.invalidateSize(),50);};
    map.on('click',event=>{if(!ward.value){setHint('Hãy chọn Phường/Xã trước khi đặt ghim trên bản đồ.',true);return;}showPosition(event.latlng.lat,event.latlng.lng,18);setHint('Đã chọn vị trí trên bản đồ; vòng tròn thể hiện bán kính sai số 10 m.');});

    async function json(url,options={}) {
        const response=await fetch(url,{headers:{Accept:'application/json',...(options.headers||{})},...options});
        const payload=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(payload.message||'Không thể kết nối GHN.');
        return payload.data;
    }
    async function calculateFee(districtId,wardCode) {
        if(!districtId||!wardCode){disableCheckout('Địa chỉ chưa có mã Quận/Huyện và Phường/Xã của GHN.');return false;}
        submitOrder.disabled=true;setStatus('Đang tính phí vận chuyển GHN...');
        try {
            const data=await json(urls.fee,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({to_district_id:Number(districtId),to_ward_code:String(wardCode),insurance_value:Math.min(subtotal,5000000)})});
            const fee=Number(data.total||0);feeOutput.textContent=money(fee);totalOutput.textContent=money(subtotal+fee);submitOrder.disabled=false;setStatus('Đã cập nhật phí vận chuyển từ GHN.','success');return true;
        } catch(error) {disableCheckout(error.message);return false;}
    }
    async function loadProvinces() {
        try {fill(province,await json(urls.provinces),'Chọn Tỉnh/Thành phố','ProvinceID','ProvinceName');}
        catch(error){fill(province,[],'Không tải được Tỉnh/Thành phố','ProvinceID','ProvinceName');setHint(error.message,true);}
    }
    province.addEventListener('change',async()=>{
        district.disabled=true;ward.disabled=true;street.disabled=true;street.value='';fill(district,[],'Đang tải Quận/Huyện...','DistrictID','DistrictName');fill(ward,[],'Chọn Quận/Huyện trước','WardCode','WardName');
        if(!province.value)return;
        try {fill(district,await json(`${urls.districts}?province_id=${encodeURIComponent(province.value)}`),'Chọn Quận/Huyện','DistrictID','DistrictName');district.disabled=false;}
        catch(error){fill(district,[],'Không tải được Quận/Huyện','DistrictID','DistrictName');setHint(error.message,true);}
    });
    district.addEventListener('change',async()=>{
        ward.disabled=true;street.disabled=true;street.value='';fill(ward,[],'Đang tải Phường/Xã...','WardCode','WardName');
        if(!district.value)return;
        try {fill(ward,await json(`${urls.wards}?district_id=${encodeURIComponent(district.value)}`),'Chọn Phường/Xã','WardCode','WardName');ward.disabled=false;}
        catch(error){fill(ward,[],'Không tải được Phường/Xã','WardCode','WardName');setHint(error.message,true);}
    });
    ward.addEventListener('change',async()=>{
        street.value='';street.disabled=!ward.value;
        if(!ward.value)return;
        setHint(`Đã chọn ${ward.selectedOptions[0].text}. Hãy nhập số nhà, tên đường hoặc địa điểm.`);calculateFee(district.value,ward.value);await locateWard();
        street.focus();
    });
    street.addEventListener('input',()=>{
        clearTimeout(addressFeeTimer);
        const specificAddress=street.value.trim();
        if(!ward.value||specificAddress.length<2)return;
        setHint('Đang tự động cập nhật phí vận chuyển...');
        addressFeeTimer=setTimeout(async()=>{
            const updated=await calculateFee(district.value,ward.value);
            const q=`${specificAddress}, ${ward.selectedOptions[0].text}, ${district.selectedOptions[0].text}, ${province.selectedOptions[0].text}, Việt Nam`;
            const positioned=await findPosition([q,`${specificAddress}, ${district.selectedOptions[0].text}, ${province.selectedOptions[0].text}, Việt Nam`]);
            if(updated)setHint(positioned?'Đã cập nhật phí và ghim địa chỉ trên bản đồ. Bạn có thể kéo ghim để chỉnh.':'Đã cập nhật phí. Hãy bấm vào bản đồ để đặt ghim chính xác.');
        },500);
    });

    function syncSavedAddress(){const option=saved?.selectedOptions?.[0];if(!option||!option.value){disableCheckout('Hãy thêm địa chỉ mới để GHN tính phí.');return;}document.querySelector('#checkout-address').value=option.dataset.address||'';document.querySelector('#checkout-district-id').value=option.dataset.districtId||'';document.querySelector('#checkout-ward-code').value=option.dataset.wardCode||'';calculateFee(option.dataset.districtId,option.dataset.wardCode);}
    saved?.addEventListener('change',()=>{if(saved.value)syncSavedAddress();else document.querySelector('#address-trigger').click();});
    document.querySelector('#address-trigger').addEventListener('click',()=>{modal.classList.add('open');setTimeout(()=>map.invalidateSize(),50);});
    const closeModal=()=>modal.classList.remove('open');document.querySelector('#address-close').addEventListener('click',closeModal);document.querySelector('#address-cancel').addEventListener('click',closeModal);
    document.querySelector('#address-submit').addEventListener('click',async()=>{const fields=[document.querySelector('#recipient-name'),document.querySelector('#recipient-phone'),province,district,ward,street];if(!fields.every(field=>field.reportValidity()))return;if(!await calculateFee(district.value,ward.value))return;const address=[street.value.trim(),ward.selectedOptions[0].text,district.selectedOptions[0].text,province.selectedOptions[0].text].filter(Boolean).join(', ');if(saved)saved.value='';document.querySelector('#checkout-name').value=fields[0].value.trim();document.querySelector('#checkout-phone').value=fields[1].value.trim();document.querySelector('#checkout-address').value=address;document.querySelector('#checkout-district-id').value=district.value;document.querySelector('#checkout-ward-code').value=ward.value;document.querySelector('#address-trigger').textContent=address;closeModal();});
    form.addEventListener('submit',event=>{if(submitOrder.disabled){event.preventDefault();setStatus('Bạn cần chọn địa chỉ và chờ GHN tính phí.','error');}});
    loadProvinces();syncSavedAddress();
})();
</script>
@endsection
