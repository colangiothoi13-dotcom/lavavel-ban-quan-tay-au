@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="address-page">
    <div class="address-heading"><div><h1>Địa chỉ của tôi</h1><p>Lưu địa chỉ nhận hàng để thanh toán nhanh hơn.</p></div><button type="button" class="address-primary" data-open-address>+ Thêm địa chỉ</button></div>
    @if(session('status'))<div class="address-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="address-error">{{ $errors->first() }}</div>@endif
    <div class="address-list">
        @forelse($addresses as $address)
            <article class="saved-address {{ $address->is_default ? 'default-address' : '' }}">
                <div><h2>{{ $address->recipient_name }} <span>{{ $address->phone }}</span></h2><p>{{ $address->full_address }}</p>@if($address->map_url)<a href="{{ $address->map_url }}" target="_blank" rel="noopener">Mở vị trí trên OpenStreetMap</a>@endif</div>
                <div class="address-actions-list">
                    @if($address->is_default)<strong class="default-label">Mặc định</strong>@else<form method="POST" action="{{ route('user.addresses.default', $address) }}">@csrf @method('PATCH')<button class="address-link" type="submit">Đặt làm mặc định</button></form>@endif
                    <form method="POST" action="{{ route('user.addresses.destroy', $address) }}" onsubmit="return confirm('Bạn có chắc muốn xóa địa chỉ này?')">@csrf @method('DELETE')<button class="delete-link" type="submit">Xóa</button></form>
                </div>
            </article>
        @empty<div class="address-empty">Bạn chưa lưu địa chỉ nhận hàng.</div>@endforelse
    </div>
</div>

<div class="address-modal {{ $errors->any() ? 'open' : '' }}" data-address-modal>
    <form class="address-dialog" method="POST" action="{{ route('user.addresses.store') }}">
        @csrf
        <button type="button" class="address-close" data-close-address aria-label="Đóng">&times;</button>
        <h2>Địa chỉ mới</h2><p>Chọn đầy đủ Tỉnh/Thành phố, Quận/Huyện và Phường/Xã theo dữ liệu GHN.</p>
        <div class="address-fields">
            <label>Họ và tên<input name="recipient_name" value="{{ old('recipient_name', auth()->user()->name) }}" required></label>
            <label>Số điện thoại<input type="tel" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required></label>
            <label>Tỉnh/Thành phố<select id="province" required><option value="">Đang tải từ GHN...</option></select></label>
            <label>Quận/Huyện<select id="district" disabled required><option value="">Chọn Tỉnh/Thành phố trước</option></select></label>
            <label class="full">Phường/Xã<select id="ward" disabled required><option value="">Chọn Quận/Huyện trước</option></select></label>
            <label class="full">Địa chỉ cụ thể
                <input id="street-address" name="street_address" value="{{ old('street_address') }}" placeholder="Chọn Phường/Xã trước" disabled required autocomplete="street-address">
                <small id="address-hint">Chọn Phường/Xã rồi nhập số nhà, tên đường hoặc địa điểm.</small>
            </label>
            <div class="address-map-wrap full"><div id="address-map" aria-label="Bản đồ khu vực giao hàng"></div><p id="map-hint">Chọn Phường/Xã để hiển thị khu vực trên bản đồ.</p></div>
            <input type="hidden" id="city" name="city" value="{{ old('city') }}">
            <input type="hidden" id="ghn-province-id" name="ghn_province_id" value="{{ old('ghn_province_id') }}">
            <input type="hidden" id="ghn-district-id" name="ghn_district_id" value="{{ old('ghn_district_id') }}">
            <input type="hidden" id="ghn-ward-code" name="ghn_ward_code" value="{{ old('ghn_ward_code') }}">
            <input type="hidden" id="map-url" name="map_url">
            <input type="hidden" id="latitude" name="latitude">
            <input type="hidden" id="longitude" name="longitude">
            <label class="check full"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))> Đặt làm địa chỉ mặc định</label>
        </div>
        <div class="address-actions"><button type="button" class="address-cancel" data-close-address>Trở lại</button><button class="address-submit" type="submit">Hoàn thành</button></div>
    </form>
</div>

<style>
.address-page{color:#334155}.address-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.address-heading h1{margin:0 0 6px;font-size:24px;color:#1e293b}.address-heading p{margin:0;color:#64748b}.address-primary,.address-submit{border:0;border-radius:4px;padding:12px 18px;background:#f45135;color:#fff;font-weight:700;cursor:pointer}.address-success,.address-error{padding:12px 14px;margin-bottom:16px;border-radius:4px}.address-success{background:#dcfce7;color:#166534}.address-error{background:#fee2e2;color:#991b1b}.address-list{display:grid;gap:12px}.saved-address{display:flex;justify-content:space-between;gap:20px;padding:18px;border:1px solid #e2e8f0;border-radius:6px;background:#fff}.saved-address h2{margin:0 0 8px;font-size:16px}.saved-address h2 span{margin-left:12px;color:#64748b;font-size:14px;font-weight:400}.saved-address p{margin:0 0 8px;color:#475569}.saved-address a,.address-link,.delete-link{color:#2563eb;background:none;border:0;padding:0;cursor:pointer;text-decoration:none}.delete-link{color:#dc2626}.address-actions-list{display:flex;align-items:flex-start;gap:14px;white-space:nowrap}.default-address{border-color:#f45135}.default-label{color:#f45135}.address-empty{padding:40px;text-align:center;color:#64748b;border:1px dashed #cbd5e1}.address-modal{display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;padding:16px;background:#0007}.address-modal.open{display:flex}.address-dialog{position:relative;width:min(700px,100%);max-height:90vh;overflow:auto;padding:28px;border-radius:6px;background:#fff}.address-dialog h2{margin:0 0 8px;font-size:26px}.address-dialog>p{margin:0 0 20px;color:#475569}.address-close{position:absolute;top:10px;right:14px;border:0;background:none;font-size:28px;cursor:pointer;color:#64748b}.address-fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}.address-fields label{display:grid;gap:6px;font-size:14px;font-weight:600}.address-fields input:not([type=checkbox]),.address-fields select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5e1;border-radius:4px;font-size:15px}.address-fields input:focus,.address-fields select:focus{border-color:#f45135;outline:2px solid #f4513524}.address-fields input:disabled,.address-fields select:disabled{background:#f1f5f9;color:#94a3b8}.address-fields .full{grid-column:1/-1}.address-fields .check{display:flex;align-items:center;gap:8px;font-weight:400}.address-fields small{color:#64748b;font-weight:400}.address-fields small.error{color:#dc2626}.address-map-wrap{overflow:hidden;border:1px solid #e2e8f0;border-radius:5px}.address-map-wrap #address-map{height:250px}.address-map-wrap p{margin:0;padding:8px 12px;background:#f8fafc;color:#64748b;font-size:12px}.address-actions{display:flex;justify-content:flex-end;gap:14px;margin-top:24px}.address-cancel{border:0;padding:12px 20px;background:#fff;color:#475569;cursor:pointer}@media(max-width:560px){.address-heading,.saved-address{align-items:flex-start;flex-direction:column}.address-fields{grid-template-columns:1fr}.address-fields .full{grid-column:auto}}
</style>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(() => {
    const ghnUrls = {
        provinces: @json(route('ghn.provinces')),
        districts: @json(route('ghn.districts')),
        wards: @json(route('ghn.wards')),
        search: @json(route('user.addresses.search'))
    };
    const modal = document.querySelector('[data-address-modal]');
    const province = document.querySelector('#province');
    const district = document.querySelector('#district');
    const ward = document.querySelector('#ward');
    const street = document.querySelector('#street-address');
    const city = document.querySelector('#city');
    const ghnProvinceId = document.querySelector('#ghn-province-id');
    const ghnDistrictId = document.querySelector('#ghn-district-id');
    const ghnWardCode = document.querySelector('#ghn-ward-code');
    const addressHint = document.querySelector('#address-hint');
    const mapHint = document.querySelector('#map-hint');
    const latitude = document.querySelector('#latitude');
    const longitude = document.querySelector('#longitude');
    const mapUrl = document.querySelector('#map-url');
    const map = L.map('address-map').setView([16.047079,108.206230],5);
    let marker, accuracyCircle, addressMapTimer;

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);

    const fill = (select, items, text, valueKey, nameKey) => { select.replaceChildren(new Option(text, '')); items.forEach(item => select.add(new Option(item[nameKey], item[valueKey]))); };
    const getJson = async url => { const response=await fetch(url,{headers:{Accept:'application/json'}}); const payload=await response.json().catch(()=>({})); if(!response.ok)throw new Error(payload.message||'Không thể kết nối GHN.'); return payload.data||[]; };
    const say = (text, error = false) => { addressHint.textContent = text; addressHint.classList.toggle('error', error); };
    const showPosition = (lat,lng,zoom=16) => {
        latitude.value=Number(lat).toFixed(7);longitude.value=Number(lng).toFixed(7);mapUrl.value=`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=18/${lat}/${lng}`;
        if(marker)marker.remove();if(accuracyCircle)accuracyCircle.remove();marker=L.marker([lat,lng],{draggable:true}).addTo(map);accuracyCircle=L.circle([lat,lng],{radius:10,color:'#f45135',fillColor:'#f45135',fillOpacity:.16,weight:2}).addTo(map);marker.on('drag',event=>accuracyCircle.setLatLng(event.target.getLatLng()));marker.on('dragend',event=>{const point=event.target.getLatLng();showPosition(point.lat,point.lng,18);});map.setView([lat,lng],zoom);mapHint.textContent='Đã ghim vị trí. Vòng tròn thể hiện bán kính 10 m; bạn có thể kéo ghim để chỉnh.';
    };
    const findPosition = async queries => {for(const q of queries){try{const response=await fetch(`${ghnUrls.search}?${new URLSearchParams({q})}`,{headers:{Accept:'application/json'}});if(!response.ok)continue;const result=((await response.json()).results||[])[0];if(result){showPosition(result.latitude,result.longitude);return true;}}catch{}}return false;};
    const locateWard = async () => {
        const wardName=ward.selectedOptions[0].text,districtName=district.selectedOptions[0].text,provinceName=province.selectedOptions[0].text;
        const found=await findPosition([`${wardName}, ${districtName}, ${provinceName}, Việt Nam`,`${districtName}, ${provinceName}, Việt Nam`,`${provinceName}, Việt Nam`]);
        if(!found){map.setView([16.047079,108.206230],5);mapHint.textContent='Bấm vào bản đồ để đặt ghim vị trí giao hàng.';}setTimeout(()=>map.invalidateSize(),50);
    };
    map.on('click',event=>{showPosition(event.latlng.lat,event.latlng.lng,18);say('Đã chọn vị trí; có thể kéo ghim để chỉnh trong bán kính 10 m.');});

    async function loadProvinces() {
        try { fill(province,await getJson(ghnUrls.provinces),'Chọn Tỉnh/Thành phố','ProvinceID','ProvinceName'); }
        catch(error) { fill(province,[],'Không tải được danh sách','ProvinceID','ProvinceName'); say(error.message,true); }
    }
    province.addEventListener('change', async () => {
        district.disabled=true; ward.disabled=true; street.disabled=true; street.value='';
        fill(district,[],province.value?'Đang tải Quận/Huyện...':'Chọn Tỉnh/Thành phố trước','DistrictID','DistrictName');
        fill(ward,[],'Chọn Quận/Huyện trước','WardCode','WardName');
        ghnProvinceId.value=province.value; ghnDistrictId.value=''; ghnWardCode.value='';
        if(!province.value)return;
        try { fill(district,await getJson(`${ghnUrls.districts}?province_id=${encodeURIComponent(province.value)}`),'Chọn Quận/Huyện','DistrictID','DistrictName'); district.disabled=false; say('Tiếp tục chọn Quận/Huyện.'); }
        catch(error) { fill(district,[],'Không tải được Quận/Huyện','DistrictID','DistrictName'); say(error.message,true); }
    });
    district.addEventListener('change', async () => {
        ward.disabled=true; street.disabled=true; street.value='';
        fill(ward,[],district.value?'Đang tải Phường/Xã...':'Chọn Quận/Huyện trước','WardCode','WardName');
        ghnDistrictId.value=district.value; ghnWardCode.value='';
        if(!district.value)return;
        try { fill(ward,await getJson(`${ghnUrls.wards}?district_id=${encodeURIComponent(district.value)}`),'Chọn Phường/Xã','WardCode','WardName'); ward.disabled=false; say('Tiếp tục chọn Phường/Xã.'); }
        catch(error) { fill(ward,[],'Không tải được Phường/Xã','WardCode','WardName'); say(error.message,true); }
    });
    ward.addEventListener('change', async () => {
        street.value=''; street.disabled=!ward.value; street.placeholder=ward.value?'Nhập số nhà, tên đường hoặc địa điểm':'Chọn Phường/Xã trước';
        ghnWardCode.value=ward.value;
        city.value=ward.value?`${ward.options[ward.selectedIndex].text}, ${district.options[district.selectedIndex].text}, ${province.options[province.selectedIndex].text}`:'';
        say(ward.value?'Đã chọn khu vực. Hãy nhập số nhà, tên đường hoặc địa điểm.':'Chọn Phường/Xã trước.'); if(ward.value){await locateWard();street.focus();}
    });
    street.addEventListener('input',()=>{clearTimeout(addressMapTimer);const specificAddress=street.value.trim();if(specificAddress.length<2||!ward.value)return;addressMapTimer=setTimeout(async()=>{const found=await findPosition([`${specificAddress}, ${ward.selectedOptions[0].text}, ${district.selectedOptions[0].text}, ${province.selectedOptions[0].text}, Việt Nam`,`${specificAddress}, ${district.selectedOptions[0].text}, ${province.selectedOptions[0].text}, Việt Nam`]);say(found?'Đã ghim địa chỉ trên bản đồ. Bạn có thể kéo ghim để chỉnh.':'Không xác định được số nhà; hãy bấm lên bản đồ để đặt ghim.',!found);},500);});
    document.querySelector('[data-open-address]').addEventListener('click',()=>{modal.classList.add('open');setTimeout(()=>map.invalidateSize(),50);});
    document.querySelectorAll('[data-close-address]').forEach(button=>button.addEventListener('click',()=>modal.classList.remove('open')));
    document.addEventListener('keydown',event=>{if(event.key==='Escape')modal.classList.remove('open');});
    loadProvinces();
})();
</script>
@endsection
