@extends('layouts.app')

@section('content')
<style>
    .admin-chat-page { color: #1e293b; }
    .admin-chat-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
    .admin-chat-heading h1 { margin: 0 0 6px; font-size: clamp(24px, 4vw, 32px); }
    .admin-chat-heading p { margin: 0; color: #64748b; }
    .admin-chat-total { display: inline-flex; gap: 8px; align-items: center; padding: 8px 12px; border-radius: 999px; background: #fef3c7; color: #92400e; font-weight: 700; white-space: nowrap; }
    .admin-chat-alert { margin: 0 0 16px; padding: 11px 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; }
    .admin-chat-alert[hidden] { display: none; }
    .admin-chat-layout { display: grid; grid-template-columns: minmax(220px, 30%) minmax(0, 1fr); min-height: min(680px, calc(100vh - 230px)); border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; background: #f8fafc; }
    .admin-chat-conversations { padding: 12px; border-right: 1px solid #e2e8f0; background: #fff; overflow-y: auto; }
    .admin-chat-conversations h2 { margin: 4px 8px 12px; font-size: 15px; }
    .admin-chat-list { display: grid; gap: 7px; }
    .admin-chat-conversation { width: 100%; padding: 12px; border: 1px solid transparent; border-radius: 10px; background: transparent; color: inherit; text-align: left; cursor: pointer; }
    .admin-chat-conversation:hover, .admin-chat-conversation.is-selected { border-color: #dbeafe; background: #eff6ff; }
    .admin-chat-conversation-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .admin-chat-conversation-name { overflow: hidden; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .admin-chat-conversation-preview { display: block; margin-top: 5px; overflow: hidden; color: #64748b; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
    .admin-chat-conversation-time { margin-top: 5px; color: #94a3b8; font-size: 11px; }
    .admin-chat-unread { min-width: 22px; padding: 2px 6px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 11px; text-align: center; }
    .admin-chat-unread[hidden], .admin-chat-empty[hidden] { display: none; }
    .admin-chat-empty { margin: 34px 8px; color: #64748b; font-size: 13px; text-align: center; }
    .admin-chat-thread { display: flex; min-width: 0; flex-direction: column; }
    .admin-chat-thread-heading { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: #fff; }
    .admin-chat-thread-heading h2 { margin: 0 0 4px; font-size: 18px; }
    .admin-chat-thread-heading p { margin: 0; color: #64748b; font-size: 13px; }
    .admin-chat-messages { flex: 1; min-height: 280px; margin: 0; padding: 24px; overflow-y: auto; list-style: none; }
    .admin-chat-message { display: flex; margin-bottom: 14px; }
    .admin-chat-message.is-mine { justify-content: flex-end; }
    .admin-chat-bubble { max-width: min(78%, 620px); padding: 11px 14px; border-radius: 14px 14px 14px 4px; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, .06); }
    .admin-chat-message.is-mine .admin-chat-bubble { border-radius: 14px 14px 4px 14px; background: #1e293b; color: #fff; }
    .admin-chat-author { display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; font-weight: 700; }
    .admin-chat-message.is-mine .admin-chat-author { color: #cbd5e1; }
    .admin-chat-body { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
    .admin-chat-time { display: block; margin-top: 6px; color: #94a3b8; font-size: 11px; }
    .admin-chat-message.is-mine .admin-chat-time { color: #cbd5e1; }
    .admin-chat-compose { display: flex; align-items: flex-end; gap: 12px; padding: 16px; border-top: 1px solid #e2e8f0; background: #fff; }
    .admin-chat-compose textarea { flex: 1; min-height: 48px; max-height: 140px; resize: vertical; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; line-height: 1.4; }
    .admin-chat-compose textarea:focus { border-color: #b4860b; outline: 2px solid rgba(180, 134, 11, .18); }
    .admin-chat-compose button { min-height: 48px; padding: 0 20px; border: 0; border-radius: 10px; background: #b4860b; color: #fff; font-weight: 700; cursor: pointer; }
    .admin-chat-compose button:disabled { cursor: wait; opacity: .6; }
    .admin-chat-compose-meta { display: flex; justify-content: space-between; grid-column: 1 / -1; color: #64748b; font-size: 12px; }
    @media (max-width: 760px) {
        .admin-chat-heading { display: block; }
        .admin-chat-total { margin-top: 14px; }
        .admin-chat-layout { grid-template-columns: 1fr; }
        .admin-chat-conversations { max-height: 230px; border-right: 0; border-bottom: 1px solid #e2e8f0; }
        .admin-chat-list { grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); }
        .admin-chat-messages { padding: 16px; }
        .admin-chat-compose { flex-wrap: wrap; }
        .admin-chat-compose textarea { flex-basis: calc(100% - 62px); }
        .admin-chat-compose button { width: 50px; padding: 0; font-size: 0; }
        .admin-chat-compose button::after { content: '➤'; font-size: 18px; }
        .admin-chat-compose-meta { width: 100%; }
    }
</style>

<section
    id="chat-admin-page"
    class="admin-chat-page"
    data-chat-page="admin"
    data-admin-id="{{ $admin->id }}"
    data-conversations-url="{{ route('chat.admin.conversations') }}"
    data-messages-template="{{ route('chat.admin.messages', ['conversation' => '__CONVERSATION__']) }}"
    data-send-template="{{ route('chat.admin.messages.store', ['conversation' => '__CONVERSATION__']) }}"
    data-read-template="{{ route('chat.admin.read', ['conversation' => '__CONVERSATION__']) }}"
    data-presence-url="{{ route('chat.admin.presence') }}"
    data-admin-channel="chat.admins"
    data-event="chat.message.sent"
    data-max-length="2000"
>
    <header class="admin-chat-heading">
        <div>
            <h1>Hộp thư khách hàng</h1>
            <p>Chọn từng cuộc hội thoại để xem và trả lời tin nhắn.</p>
        </div>
        <div class="admin-chat-total">Chưa đọc: <span data-admin-unread>0</span></div>
    </header>

    <div class="admin-chat-alert" data-chat-error role="alert" hidden></div>

    <div class="admin-chat-layout">
        <aside class="admin-chat-conversations" aria-label="Danh sách cuộc hội thoại">
            <h2>Cuộc hội thoại</h2>
            <div class="admin-chat-list" data-admin-conversations></div>
            <p class="admin-chat-empty" data-admin-empty>Chưa có cuộc hội thoại nào.</p>
        </aside>
        <section class="admin-chat-thread" aria-labelledby="admin-chat-selected-user">
            <header class="admin-chat-thread-heading">
                <h2 id="admin-chat-selected-user" data-admin-selected-user>Chọn một khách hàng</h2>
                <p data-admin-selected-hint>Những tin nhắn mới sẽ xuất hiện theo thời gian thực.</p>
            </header>
            <ol class="admin-chat-messages" data-chat-messages aria-live="polite" aria-label="Tin nhắn trong cuộc hội thoại"></ol>
            <p class="admin-chat-empty" data-chat-empty>Chọn cuộc hội thoại để bắt đầu hỗ trợ.</p>
            <form class="admin-chat-compose" data-chat-form>
                <textarea name="body" data-chat-input maxlength="2000" placeholder="Trả lời khách hàng..." aria-label="Nội dung trả lời" required disabled></textarea>
                <button type="submit" data-chat-submit disabled>Gửi</button>
                <div class="admin-chat-compose-meta">
                    <span>Tin nhắn tối đa 2000 ký tự.</span>
                    <span data-chat-count>0/2000</span>
                </div>
            </form>
        </section>
    </div>
</section>

@vite(['resources/js/app.js'])
@endsection
