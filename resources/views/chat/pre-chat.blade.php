<style>
    .chat-prechat {
        padding: 24px;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        background: #fff;
        color: #1e293b;
    }
    .chat-prechat[hidden] { display: none; }
    .chat-prechat h3 { margin: 0 0 8px; font-size: 18px; }
    .chat-prechat p { margin: 0 0 18px; color: #64748b; line-height: 1.5; }
    .chat-prechat-form { display: grid; gap: 12px; }
    .chat-prechat-field { display: grid; gap: 6px; }
    .chat-prechat-field label { color: #334155; font-size: 13px; font-weight: 700; }
    .chat-prechat-field input {
        width: 100%;
        padding: 11px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        color: #1e293b;
        font: inherit;
        background: #fff;
    }
    .chat-prechat-field input:focus {
        border-color: #2563eb;
        outline: 2px solid rgba(37, 99, 235, .15);
    }
    .chat-prechat-form button {
        min-height: 44px;
        margin-top: 4px;
        border: 0;
        border-radius: 8px;
        background: #2563eb;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }
    .chat-prechat-form button:disabled { cursor: wait; opacity: .65; }
    .chat-prechat-error {
        margin: 0;
        padding: 10px 12px;
        border: 1px solid #fecaca;
        border-radius: 8px;
        background: #fef2f2;
        color: #991b1b;
        font-size: 13px;
    }
    .chat-prechat-error[hidden] { display: none; }
</style>

<div class="chat-prechat" data-chat-prechat>
    <h3>Trước khi bắt đầu chat</h3>
    <p>Bạn vui lòng để lại thông tin để shop tiện tư vấn và liên hệ lại khi cần.</p>
    <form class="chat-prechat-form" data-chat-prechat-form>
        <div class="chat-prechat-field">
            <label for="chat-lead-name-{{ $chatFormId ?? 'user' }}">Họ và tên</label>
            <input
                id="chat-lead-name-{{ $chatFormId ?? 'user' }}"
                type="text"
                name="name"
                data-chat-lead-name
                maxlength="255"
                autocomplete="name"
                required
            >
        </div>
        <div class="chat-prechat-field">
            <label for="chat-lead-phone-{{ $chatFormId ?? 'user' }}">Số điện thoại</label>
            <input
                id="chat-lead-phone-{{ $chatFormId ?? 'user' }}"
                type="tel"
                name="phone"
                data-chat-lead-phone
                maxlength="30"
                autocomplete="tel"
                inputmode="tel"
                placeholder="0901234567"
                required
            >
        </div>
        <button type="submit" data-chat-start>Bắt đầu trò chuyện</button>
        <p class="chat-prechat-error" data-chat-prechat-error role="alert" hidden></p>
    </form>
</div>
