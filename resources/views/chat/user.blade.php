@extends('layouts.app')

@section('content')
<style>
    .chat-page { color: #1e293b; max-width: 980px; margin: 0 auto; }
    .chat-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
    .chat-heading h1 { margin: 0 0 6px; font-size: clamp(24px, 4vw, 32px); }
    .chat-heading p { margin: 0; color: #64748b; }
    .chat-status { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 14px; white-space: nowrap; }
    .chat-status::before { content: ''; width: 9px; height: 9px; border-radius: 50%; background: #94a3b8; }
    .chat-status.is-online { color: #166534; background: #dcfce7; }
    .chat-status.is-online::before { background: #22c55e; }
    .chat-status.is-busy { color: #92400e; background: #fef3c7; }
    .chat-status.is-busy::before { background: #f59e0b; }
    .chat-unread { margin-top: 10px; color: #475569; font-size: 14px; }
    .chat-unread strong { color: #1e293b; }
    .chat-alert { margin: 0 0 16px; padding: 11px 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; }
    .chat-alert[hidden], .chat-empty[hidden] { display: none; }
    .chat-panel { display: flex; flex-direction: column; height: calc(100vh - 240px); border: 1px solid #e2e8f0; border-radius: 14px; background: #f8fafc; overflow: hidden; }
    .chat-messages { flex: 1; min-height: 0; margin: 0; padding: 24px; overflow-y: auto; list-style: none; }
    .chat-empty { margin: 90px auto; color: #64748b; text-align: center; }
    .chat-message { display: flex; margin: 0 0 14px; }
    .chat-message.is-mine { justify-content: flex-end; }
    .chat-bubble { max-width: min(78%, 620px); padding: 11px 14px; border-radius: 14px 14px 14px 4px; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, .06); }
    .chat-message.is-mine .chat-bubble { border-radius: 14px 14px 4px 14px; background: #1e293b; color: #fff; }
    .chat-author { display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; font-weight: 700; }
    .chat-message.is-mine .chat-author { color: #cbd5e1; }
    .chat-body { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
    .chat-time { display: block; margin-top: 6px; color: #94a3b8; font-size: 11px; }
    .chat-message.is-mine .chat-time { color: #cbd5e1; }
    .chat-compose { display: flex; align-items: flex-end; gap: 12px; padding: 16px; border-top: 1px solid #e2e8f0; background: #fff; }
    .chat-compose textarea { flex: 1; min-height: 48px; max-height: 140px; resize: vertical; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; color: #1e293b; font: inherit; line-height: 1.4; }
    .chat-compose textarea:focus { border-color: #b4860b; outline: 2px solid rgba(180, 134, 11, .18); }
    .chat-compose button { min-height: 48px; padding: 0 20px; border: 0; border-radius: 10px; background: #b4860b; color: #fff; font-weight: 700; cursor: pointer; }
    .chat-compose button:disabled { cursor: wait; opacity: .6; }
    .chat-compose-meta { display: flex; justify-content: space-between; grid-column: 1 / -1; color: #64748b; font-size: 12px; }
    @media (max-width: 640px) {
        .chat-heading { display: block; }
        .chat-status { margin-top: 14px; }
        .chat-messages { padding: 16px; }
        .chat-compose { flex-wrap: wrap; }
        .chat-compose textarea { flex-basis: calc(100% - 62px); }
        .chat-compose button { width: 50px; padding: 0; font-size: 0; }
        .chat-compose button::after { content: '➤'; font-size: 18px; }
        .chat-compose-meta { width: 100%; }
    }
</style>

<section
    id="chat-user-page"
    class="chat-page"
    data-chat-page="user"
    data-chat-user-id="{{ $user->id }}"
    data-overview-url="{{ route('chat.user.overview') }}"
    data-messages-url="{{ route('chat.user.messages') }}"
    data-send-url="{{ route('chat.user.messages.store') }}"
    data-read-url="{{ route('chat.user.read') }}"
    data-status-url="{{ route('chat.user.admin-status') }}"
    data-channel="chat.user.{{ $user->id }}"
    data-event="chat.message.sent"
    data-max-length="2000"
>
    <header class="chat-heading">
        <div>
            <h1>Nhắn tin với admin</h1>
            <p>Trao đổi riêng với đội ngũ hỗ trợ của shop.</p>
        </div>
        <div>
            <div class="chat-status is-busy" data-chat-status role="status" aria-live="polite">Admin đang bận, sẽ trả lời sau</div>
            <div class="chat-unread" aria-live="polite">Chưa đọc: <strong data-chat-unread-count>0</strong></div>
        </div>
    </header>

    <div class="chat-alert" data-chat-error role="alert" hidden></div>

    <div class="chat-panel">
        <ol class="chat-messages" data-chat-messages aria-live="polite" aria-label="Lịch sử tin nhắn"></ol>
        <p class="chat-empty" data-chat-empty>Chưa có tin nhắn. Bạn hãy gửi lời nhắn đầu tiên nhé.</p>
        <form class="chat-compose" data-chat-form>
            <textarea name="body" data-chat-input maxlength="2000" placeholder="Viết tin nhắn..." aria-label="Nội dung tin nhắn" required></textarea>
            <button type="submit" data-chat-submit>Gửi</button>
            <div class="chat-compose-meta">
                <span>Tin nhắn tối đa 2000 ký tự.</span>
                <span data-chat-count>0/2000</span>
            </div>
        </form>
    </div>
</section>

@vite(['resources/js/app.js'])
@endsection
