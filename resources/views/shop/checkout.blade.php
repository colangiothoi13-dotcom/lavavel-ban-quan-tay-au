@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="checkout-page">
    <style>
        .checkout-page { padding: 0; color: #334155; }
        .address-trigger { width: 100%; padding: 16px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; text-align: left; cursor: pointer; color: #64748b; }
        .address-trigger.has-address { color: #334155; }
        .address-modal { display: none; position: fixed; inset: 0; z-index: 1000; align-items: center; justify-content: center; padding: 16px; background: #0f172a73; }
        .address-modal.open { display: flex; }
        .address-dialog { width: min(720px, 100%); max-height: 92vh; overflow: auto; padding: 28px; background: #fff; border-radius: 8px; box-shadow: 0 12px 35px #0f172a40; }
        .address-dialog h2 { margin: 0 0 8px; }
        .address-dialog > p { margin: 0 0 22px; color: #64748b; }
        .address-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .address-field.full, .map-wrap { grid-column: 1 / -1; }
        .address-field { position: relative; }
        .address-field label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; }
        .address-field input, .address-field select { box-sizing: border-box; width: 100%; padding: 13px 14px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; font-size: 16px; }
        .address-field input:focus, .address-field select:focus { border-color: #f45135; outline: 2px solid #f4513524; }
        .address-field input:disabled, .address-field select:disabled { cursor: not-allowed; background: #f1f5f9; color: #94a3b8; }
        .field-hint { min-height: 18px; margin: 5px 0 0; color: #64748b; font-size: 12px; }
        .field-hint.error { color: #dc2626; }
        .address-suggestions { position: absolute; z-index: 1100; top: 76px; left: 0; right: 0; display: none; max-height: 230px; overflow-y: auto; margin: 0; padding: 0; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; box-shadow: 0 10px 24px #0f172a26; list-style: none; }
        .address-suggestions.open { display: block; }
        .address-suggestions button { width: 100%; padding: 11px 13px; border: 0; border-bottom: 1px solid #f1f5f9; background: #fff; text-align: left; cursor: pointer; color: #334155; }
        .address-suggestions button:hover, .address-suggestions button:focus { background: #fff7ed; outline: 0; }
        .map-wrap { position: relative; min-height: 230px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background: #f8fafc; }
        #address-map { height: 230px; }
        .map-placeholder { position: absolute; z-index: 500; inset: 0; display: grid; place-items: center; padding: 24px; text-align: center; color: #94a3b8; background: #f8fafc; }
        .map-placeholder[hidden] { display: none; }
        .address-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 24px; }
        .address-actions button { padding: 12px 28px; border: 0; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .address-cancel { background: #fff; color: #475569; }
        .address-submit { background: #f45135; color: #fff; font-weight: 700; }
        @media (max-width: 560px) { .address-grid { grid-template-columns: 1fr; } .address-field.full, .map-wrap { grid-column: auto; } .address-dialog { padding: 22px 18px; } }
    </style>

    <h1 style="font-size:22px;margin-bottom:6px;color:#1e293b">Thông tin thanh toán</h1>
    <p style="color:#64748b;margin-bottom:20px">Kiểm tra thông tin nhận hàng rồi xác nhận đặt hàng.</p>

    <form method="POST" action="{{ route('checkout.place') }}">
        @csrf
        @foreach(request()->input('selected_items', []) as $selectedId)
            <input type="hidden" name="selected_items[]" value="{{ $selectedId }}">
        @endforeach

        <div style="margin-bottom:24px">
            <label style="display:block;font-weight:600;margin-bottom:6px">Địa chỉ nhận hàng</label>
            @php($defaultAddress = $addresses->firstWhere('is_default', true))
            <select name="saved_address_id" id="saved-address" style="width:100%;padding:14px;border:1px solid #cbd5e1;border-radius:6px" @if(!$addresses->count()) hidden @endif>
                @foreach($addresses as $savedAddress)
                    <option value="{{ $savedAddress->id }}" @selected(old('saved_address_id', $defaultAddress?->id) == $savedAddress->id)>{{ $savedAddress->recipient_name }} - {{ $savedAddress->full_address }}{{ $savedAddress->is_default ? ' (Mặc định)' : '' }}</option>
                @endforeach
            </select>
            <button type="button" class="address-trigger" id="address-trigger" @if($addresses->count()) style="margin-top:10px" @endif>{{ $defaultAddress ? 'Thêm địa chỉ khác' : 'Thêm địa chỉ nhận hàng' }}</button>
            <input type="hidden" name="address" id="address" value="{{ old('address', $defaultAddress?->full_address) }}" required>
        </div>

        <div style="margin-bottom:24px">
            <label style="display:block;font-weight:600;margin-bottom:10px">Phương thức thanh toán</label>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <label style="border:1px solid #cbd5e1;border-radius:6px;padding:12px 16px"><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash') required> Tiền mặt khi nhận hàng</label>
                <label style="border:1px solid #cbd5e1;border-radius:6px;padding:12px 16px"><input type="radio" name="payment_method" value="bank_transfer" @checked(old('payment_method') === 'bank_transfer')> Chuyển khoản ngân hàng</label>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e2e8f0;padding-top:20px">
            <h3>Tổng đơn hàng: <span style="color:#ea580c">{{ number_format($items->sum('total')) }} đ</span></h3>
            <button type="submit" style="background:#ea580c;color:#fff;border:0;padding:12px 24px;font-weight:bold;border-radius:6px">Xác nhận đặt hàng</button>
        </div>
    </form>

    <div class="address-modal" id="address-modal" role="dialog" aria-modal="true" aria-labelledby="address-title">
        <div class="address-dialog">
            <h2 id="address-title">Địa chỉ mới</h2>
            <p>Chọn Tỉnh/Thành phố và Phường/Xã trước, sau đó tìm địa chỉ cụ thể trên OpenStreetMap.</p>
            <div class="address-grid">
                <div class="address-field"><label for="recipient-name">Họ và tên</label><input id="recipient-name" value="{{ old('name', auth()->user()?->name) }}" autocomplete="name" required></div>
                <div class="address-field"><label for="recipient-phone">Số điện thoại</label><input id="recipient-phone" type="tel" value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" required></div>
                <div class="address-field"><label for="province">Tỉnh/Thành phố</label><select id="province" required><option value="">Đang tải danh sách...</option></select></div>
                <div class="address-field"><label for="ward">Phường/Xã</label><select id="ward" disabled required><option value="">Chọn Tỉnh/Thành phố trước</option></select></div>
                <div class="address-field full">
                    <label for="street-address">Địa chỉ cụ thể</label>
                    <input id="street-address" placeholder="Chọn Phường/Xã trước" autocomplete="off" disabled required aria-autocomplete="list" aria-controls="address-suggestions">
                    <ul class="address-suggestions" id="address-suggestions" role="listbox"></ul>
                    <p class="field-hint" id="address-hint">OpenStreetMap sẽ gợi ý địa chỉ sau khi bạn chọn Phường/Xã.</p>
                </div>
                <div class="map-wrap"><div id="address-map" aria-label="Bản đồ vị trí giao hàng"></div><div class="map-placeholder" id="map-placeholder">Bản đồ sẽ hiện sau khi bạn chọn địa chỉ cụ thể.</div></div>
            </div>
            <div class="address-actions"><button type="button" class="address-cancel" id="address-cancel">Trở lại</button><button type="button" class="address-submit" id="address-submit">Hoàn thành</button></div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(() => {
    const provinceApi = 'https://provinces.open-api.vn/api/v2';
    const photonApi = 'https://photon.komoot.io/api/';
    const modal = document.querySelector('#address-modal');
    const trigger = document.querySelector('#address-trigger');
    const address = document.querySelector('#address');
    const saved = document.querySelector('#saved-address');
    const province = document.querySelector('#province');
    const ward = document.querySelector('#ward');
    const street = document.querySelector('#street-address');
    const suggestions = document.querySelector('#address-suggestions');
    const hint = document.querySelector('#address-hint');
    const placeholder = document.querySelector('#map-placeholder');
    const name = document.querySelector('#recipient-name');
    const phone = document.querySelector('#recipient-phone');
    const savedData = @json($addresses->keyBy('id')->map(fn ($item) => ['address' => $item->full_address]));
    const map = L.map('address-map', { zoomControl: true }).setView([16.047079, 108.206230], 5);
    let marker = null;
    let selectedPlace = false;
    let searchTimer = null;
    let searchController = null;

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const say = (text, error = false) => { hint.textContent = text; hint.classList.toggle('error', error); };
    const fill = (select, items, text) => {
        select.innerHTML = '';
        select.add(new Option(text, ''));
        items.forEach(item => select.add(new Option(item.name, item.code)));
    };
    const closeSuggestions = () => { suggestions.classList.remove('open'); suggestions.innerHTML = ''; };
    const resetAddress = () => {
        street.value = '';
        selectedPlace = false;
        placeholder.hidden = false;
        closeSuggestions();
        if (marker) { marker.remove(); marker = null; }
    };

    async function loadProvinces() {
        try {
            const response = await fetch(`${provinceApi}/p/`);
            if (!response.ok) throw new Error();
            fill(province, await response.json(), 'Chọn Tỉnh/Thành phố');
        } catch (error) {
            fill(province, [], 'Không tải được danh sách');
            say('Không thể tải dữ liệu hành chính. Vui lòng tải lại trang.', true);
        }
    }

    province.addEventListener('change', async () => {
        ward.disabled = true;
        fill(ward, [], province.value ? 'Đang tải Phường/Xã...' : 'Chọn Tỉnh/Thành phố trước');
        street.disabled = true;
        street.placeholder = 'Chọn Phường/Xã trước';
        resetAddress();
        if (!province.value) return;
        try {
            const response = await fetch(`${provinceApi}/p/${encodeURIComponent(province.value)}?depth=2`);
            if (!response.ok) throw new Error();
            const data = await response.json();
            fill(ward, data.wards || [], 'Chọn Phường/Xã');
            ward.disabled = false;
            say('Tiếp tục chọn Phường/Xã.');
        } catch (error) {
            fill(ward, [], 'Không tải được danh sách');
            say('Không thể tải Phường/Xã. Vui lòng chọn lại Tỉnh/Thành phố.', true);
        }
    });

    ward.addEventListener('change', () => {
        resetAddress();
        street.disabled = !ward.value;
        street.placeholder = ward.value ? 'Nhập số nhà, tên đường hoặc địa điểm' : 'Chọn Phường/Xã trước';
        say(ward.value ? 'Gõ địa chỉ, các địa điểm quanh Phường/Xã sẽ được gợi ý.' : 'OpenStreetMap sẽ gợi ý địa chỉ sau khi bạn chọn Phường/Xã.');
        if (ward.value) street.focus();
    });

    function featureLabel(feature) {
        const p = feature.properties || {};
        return [p.name, p.street, p.district, p.city, p.state, p.country].filter((value, index, values) => value && values.indexOf(value) === index).join(', ');
    }

    function chooseFeature(feature) {
        const [lng, lat] = feature.geometry.coordinates;
        street.value = featureLabel(feature);
        selectedPlace = true;
        closeSuggestions();
        if (marker) marker.remove();
        marker = L.marker([lat, lng]).addTo(map);
        map.setView([lat, lng], 17);
        placeholder.hidden = true;
        say('Đã chọn vị trí trên OpenStreetMap.');
    }

    street.addEventListener('input', () => {
        selectedPlace = false;
        clearTimeout(searchTimer);
        searchController?.abort();
        closeSuggestions();
        const keyword = street.value.trim();
        if (keyword.length < 2 || !ward.value) return;
        searchTimer = setTimeout(async () => {
            const provinceName = province.options[province.selectedIndex].text;
            const wardName = ward.options[ward.selectedIndex].text;
            const query = `${keyword}, ${wardName}, ${provinceName}, Việt Nam`;
            searchController = new AbortController();
            say('Đang tìm địa chỉ trên OpenStreetMap...');
            try {
                const params = new URLSearchParams({ q: query, limit: '5', lang: 'vi' });
                const response = await fetch(`${photonApi}?${params}`, { signal: searchController.signal });
                if (!response.ok) throw new Error();
                const features = (await response.json()).features || [];
                if (!features.length) { say('Không tìm thấy địa chỉ phù hợp. Bạn thử nhập tên đường hoặc địa điểm gần đó.', true); return; }
                features.forEach(feature => {
                    const item = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = featureLabel(feature);
                    button.addEventListener('click', () => chooseFeature(feature));
                    item.append(button);
                    suggestions.append(item);
                });
                suggestions.classList.add('open');
                say('Chọn một địa chỉ trong danh sách gợi ý.');
            } catch (error) {
                if (error.name !== 'AbortError') say('Dịch vụ tìm kiếm đang bận. Vui lòng thử lại.', true);
            }
        }, 450);
    });

    const closeModal = () => { modal.classList.remove('open'); closeSuggestions(); };
    trigger.addEventListener('click', () => { modal.classList.add('open'); name.focus(); setTimeout(() => map.invalidateSize(), 50); });
    saved?.addEventListener('change', () => { if (savedData[saved.value]) address.value = savedData[saved.value].address; });
    document.querySelector('#address-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    document.addEventListener('click', event => { if (!suggestions.contains(event.target) && event.target !== street) closeSuggestions(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });

    document.querySelector('#address-submit').addEventListener('click', () => {
        if (![name, phone, province, ward, street].every(field => field.reportValidity())) return;
        if (!selectedPlace) { say('Bạn hãy chọn một địa chỉ trong danh sách gợi ý OpenStreetMap.', true); street.focus(); return; }
        const form = trigger.closest('form');
        form.querySelectorAll('[data-new-address]').forEach(element => element.remove());
        [['name', name.value], ['phone', phone.value]].forEach(([key, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = key; input.value = value.trim(); input.dataset.newAddress = '1'; form.append(input);
        });
        const provinceName = province.options[province.selectedIndex].text;
        const wardName = ward.options[ward.selectedIndex].text;
        address.value = [street.value.trim(), wardName, provinceName].filter((value, index, values) => value && !values.slice(0, index).some(existing => existing.includes(value))).join(', ');
        if (saved) saved.value = '';
        trigger.textContent = address.value;
        trigger.classList.add('has-address');
        closeModal();
    });

    if (address.value) { trigger.textContent = address.value; trigger.classList.add('has-address'); }
    loadProvinces();
})();
</script>
@endsection
