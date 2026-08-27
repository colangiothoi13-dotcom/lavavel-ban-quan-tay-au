@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="address-page">
    <div class="address-heading"><div><h1>Địa chỉ của tôi</h1><p>Lưu địa chỉ nhận hàng để thanh toán nhanh hơn.</p></div><button type="button" class="address-primary" data-open-address>+ Thêm địa chỉ</button></div>
    @if(session('status'))<div class="address-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="address-error">{{ $errors->first() }}</div>@endif
    <div class="address-list">
        @forelse($addresses as $address)
            <article class="saved-address {{ $address->is_default ? 'default-address' : '' }}"><div><h2>{{ $address->recipient_name }} <span>{{ $address->phone }}</span></h2><p>{{ $address->full_address }}</p>@if($address->map_url)<a href="{{ $address->map_url }}" target="_blank" rel="noopener">Mở vị trí trên OpenStreetMap</a>@endif</div><div class="address-actions-list">@if($address->is_default)<strong class="default-label">Mặc định</strong>@else<form method="POST" action="{{ route('user.addresses.default', $address) }}">@csrf @method('PATCH')<button class="address-link" type="submit">Đặt làm mặc định</button></form>@endif<form method="POST" action="{{ route('user.addresses.destroy', $address) }}" onsubmit="return confirm('Bạn có chắc muốn xóa địa chỉ này?')">@csrf @method('DELETE')<button class="delete-link" type="submit">Xóa</button></form></div></article>
        @empty<div class="address-empty">Bạn chưa lưu địa chỉ nhận hàng.</div>@endforelse
    </div>
</div>

<div class="address-modal" data-address-modal>
    <form class="address-dialog" method="POST" action="{{ route('user.addresses.store') }}">
        @csrf
        <button type="button" class="address-close" data-close-address aria-label="Đóng">&times;</button>
        <h2>Địa chỉ mới</h2><p>Chọn tỉnh, phường/xã rồi tìm địa chỉ trên OpenStreetMap.</p>
        <div class="address-fields">
            <label>Họ và tên<input name="recipient_name" value="{{ old('recipient_name', auth()->user()->name) }}" required></label>
            <label>Số điện thoại<input type="tel" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required></label>
            <label>Tỉnh/Thành phố<select id="province" required><option value="">Đang tải danh sách...</option></select></label>
            <label>Phường/Xã<select id="ward" disabled required><option value="">Chọn Tỉnh/Thành phố trước</option></select></label>
            <label class="full">Địa chỉ cụ thể<input id="street-address" name="street_address" placeholder="Chọn Phường/Xã trước" disabled required autocomplete="off" list="address-suggestions"><datalist id="address-suggestions"></datalist></label>
            <div class="address-map-wrap full"><div id="address-map"></div><p id="map-hint">Bản đồ sẽ hiện sau khi bạn chọn địa chỉ.</p></div>
            <input type="hidden" id="city" name="city"><input type="hidden" id="map-url" name="map_url"><input type="hidden" id="latitude" name="latitude"><input type="hidden" id="longitude" name="longitude">
            <label class="check full"><input type="checkbox" name="is_default" value="1"> Đặt làm địa chỉ mặc định</label>
        </div>
        <div class="address-actions"><button type="button" class="address-cancel" data-close-address>Trở lại</button><button class="address-submit" type="submit">Hoàn thành</button></div>
    </form>
</div>

<style>
.address-page{color:#334155}.address-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.address-heading h1{margin:0 0 6px;font-size:24px;color:#1e293b}.address-heading p{margin:0;color:#64748b}.address-primary,.address-submit{border:0;border-radius:4px;padding:12px 18px;background:#f45135;color:white;font-weight:700;cursor:pointer}.address-success,.address-error{padding:12px 14px;margin-bottom:16px;border-radius:4px}.address-success{background:#dcfce7;color:#166534}.address-error{background:#fee2e2;color:#991b1b}.address-list{display:grid;gap:12px}.saved-address{display:flex;justify-content:space-between;gap:20px;padding:18px;border:1px solid #e2e8f0;border-radius:6px;background:#fff}.saved-address h2{margin:0 0 8px;font-size:16px}.saved-address h2 span{margin-left:12px;color:#64748b;font-size:14px;font-weight:400}.saved-address p{margin:0 0 8px;color:#475569}.saved-address a,.address-link,.delete-link{color:#2563eb;background:none;border:0;padding:0;cursor:pointer;text-decoration:none}.delete-link{color:#dc2626}.address-actions-list{display:flex;align-items:flex-start;gap:14px;white-space:nowrap}.default-address{border-color:#f45135}.default-label{color:#f45135;white-space:nowrap}.address-empty{padding:40px;text-align:center;color:#64748b;border:1px dashed #cbd5e1}.address-modal{display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;padding:16px;background:#0007}.address-modal.open{display:flex}.address-dialog{position:relative;width:min(700px,100%);max-height:90vh;overflow:auto;padding:28px;border-radius:6px;background:white}.address-dialog h2{margin:0 0 8px;font-size:26px}.address-dialog p{margin:0 0 20px;color:#475569}.address-close{position:absolute;top:10px;right:14px;border:0;background:none;font-size:28px;cursor:pointer;color:#64748b}.address-fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}.address-fields label{display:grid;gap:6px;font-size:14px;font-weight:600}.address-fields input:not([type=checkbox]),.address-fields select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5e1;border-radius:4px;font-size:15px}.address-fields input:disabled,.address-fields select:disabled{background:#f1f5f9;color:#94a3b8}.address-fields .full{grid-column:1/-1}.address-fields .check{display:flex;align-items:center;gap:8px;font-weight:400}.address-map-wrap{grid-column:1/-1;border:1px solid #e2e8f0;border-radius:4px;overflow:hidden}.address-map-wrap #address-map{height:230px}.address-map-wrap p{margin:0;padding:8px 12px;background:#f8fafc;font-size:12px;color:#64748b}.address-actions{display:flex;justify-content:flex-end;gap:14px;margin-top:24px}.address-cancel{border:0;padding:12px 20px;background:white;color:#475569;cursor:pointer}@media(max-width:560px){.address-heading,.saved-address{align-items:flex-start;flex-direction:column}.address-fields{grid-template-columns:1fr}.address-fields .full{grid-column:auto}}
</style>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){const provinceApi='https://provinces.open-api.vn/api/v2',searchApi='https://photon.komoot.io/api/',modal=document.querySelector('[data-address-modal]'),province=document.querySelector('#province'),ward=document.querySelector('#ward'),street=document.querySelector('#street-address'),city=document.querySelector('#city'),hint=document.querySelector('#map-hint'),suggestions=document.querySelector('#address-suggestions'),map=L.map('address-map').setView([16.047079,108.20623],5);let marker,selected=false,timer,controller;L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);function fill(select,items,text){select.innerHTML='';select.add(new Option(text,''));items.forEach(item=>select.add(new Option(item.name,item.code)))}function clearPlace(){selected=false;suggestions.replaceChildren();['latitude','longitude','map-url'].forEach(id=>document.querySelector('#'+id).value='');if(marker){marker.remove();marker=null}}function label(feature){const p=feature.properties||{};return[p.name,p.street,p.district,p.city,p.state,p.country].filter((v,i,a)=>v&&a.indexOf(v)===i).join(', ')}function choose(feature){const[lng,lat]=feature.geometry.coordinates;street.value=label(feature);selected=true;document.querySelector('#latitude').value=lat;document.querySelector('#longitude').value=lng;document.querySelector('#map-url').value='https://www.openstreetmap.org/?mlat='+lat+'&mlon='+lng+'#map=18/'+lat+'/'+lng;if(marker)marker.remove();marker=L.marker([lat,lng]).addTo(map);map.setView([lat,lng],17);hint.textContent='Đã chọn vị trí trên OpenStreetMap.'}async function provinces(){const response=await fetch(provinceApi+'/p/');fill(province,await response.json(),'Chọn Tỉnh/Thành phố')}province.addEventListener('change',async()=>{clearPlace();street.value='';street.disabled=true;ward.disabled=true;fill(ward,[],'Đang tải Phường/Xã...');if(!province.value)return;const data=await(await fetch(provinceApi+'/p/'+province.value+'?depth=2')).json();fill(ward,data.wards||[],'Chọn Phường/Xã');ward.disabled=false;city.value=province.options[province.selectedIndex].text});ward.addEventListener('change',()=>{clearPlace();street.value='';street.disabled=!ward.value;city.value=ward.value?ward.options[ward.selectedIndex].text+', '+province.options[province.selectedIndex].text:province.options[province.selectedIndex].text;street.placeholder=ward.value?'Nhập số nhà, tên đường hoặc địa điểm':'Chọn Phường/Xã trước'});street.addEventListener('input',()=>{clearPlace();clearTimeout(timer);controller?.abort();if(street.value.trim().length<2||!ward.value)return;timer=setTimeout(async()=>{controller=new AbortController();const q=street.value+', '+ward.options[ward.selectedIndex].text+', '+province.options[province.selectedIndex].text+', Việt Nam';const response=await fetch(searchApi+'?'+new URLSearchParams({q:q,limit:5,lang:'vi'}),{signal:controller.signal});const features=(await response.json()).features||[];suggestions.replaceChildren(...features.slice(0,5).map(feature=>{const option=document.createElement('option');option.value=label(feature);return option;}));if(features[0])choose(features[0]);else hint.textContent='Không tìm thấy địa chỉ trên OpenStreetMap.'},450)});document.querySelector('[data-open-address]').addEventListener('click',()=>{modal.classList.add('open');setTimeout(()=>map.invalidateSize(),50)});document.querySelectorAll('[data-close-address]').forEach(button=>button.addEventListener('click',()=>modal.classList.remove('open')));provinces().catch(()=>fill(province,[],'Không tải được danh sách'))}());
</script>
<script>
(function () {
    const input = document.querySelector('#street-address');
    const list = document.querySelector('#address-suggestions');
    const province = document.querySelector('#province');
    const ward = document.querySelector('#ward');
    if (!input || !list || !province || !ward) return;
    let timer;
    let controller;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        controller?.abort();
        list.replaceChildren();
        const keyword = input.value.trim();
        if (keyword.length < 2 || !ward.value) return;
        timer = setTimeout(async function () {
            controller = new AbortController();
            const query = `${keyword}, ${ward.options[ward.selectedIndex].text}, ${province.options[province.selectedIndex].text}, Việt Nam`;
            try {
                const response = await fetch(`https://photon.komoot.io/api/?${new URLSearchParams({ q: query, limit: '5', lang: 'vi' })}`, { signal: controller.signal });
                const features = (await response.json()).features || [];
                list.replaceChildren(...features.slice(0, 5).map(feature => { const option = document.createElement('option'); option.value = [feature.properties?.name, feature.properties?.street, feature.properties?.city, feature.properties?.state].filter((value, index, values) => value && values.indexOf(value) === index).join(', '); return option; }));
            } catch (error) {
                if (error.name !== 'AbortError') list.replaceChildren();
            }
        }, 350);
    });
}());
</script>
@endsection
