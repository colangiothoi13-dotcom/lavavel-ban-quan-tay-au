import { createEcho } from './echo';

const MAX_MESSAGE_LENGTH = 2000;
const STATUS_POLL_INTERVAL = 30000;
const USER_CHAT_REFRESH_INTERVAL = 5000;
const ADMIN_CHAT_REFRESH_INTERVAL = 5000;
const REALTIME_EVENT = '.chat.message.sent';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function requestJson(url, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
    };
    const token = csrfToken();
    if (token) headers['X-CSRF-TOKEN'] = token;
    if (options.body && !headers['Content-Type']) headers['Content-Type'] = 'application/json';

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers,
    });
    let payload = {};
    try {
        payload = await response.json();
    } catch {
        payload = {};
    }

    if (!response.ok) {
        const validationMessage = Object.values(payload.errors || {})[0]?.[0];
        throw new Error(validationMessage || payload.message || 'Không thể thực hiện yêu cầu.');
    }

    return payload;
}

function showError(element, message) {
    if (!element) return;
    element.textContent = message;
    element.hidden = false;
}

function clearError(element) {
    if (!element) return;
    element.textContent = '';
    element.hidden = true;
}

function setCount(input, output, maxLength) {
    if (output) output.textContent = `${input.value.length}/${maxLength}`;
}

function submitOnEnter(input, form) {
    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

        event.preventDefault();
        form.requestSubmit();
    });
}

function formatTimestamp(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
}

function appendMessage(container, emptyElement, seenIds, message, currentUserId, classPrefix) {
    if (!message || message.id == null) return false;
    const messageId = String(message.id);
    if (seenIds.has(messageId)) return false;
    seenIds.add(messageId);

    const item = document.createElement('li');
    const isMine = String(message.sender_id) === String(currentUserId);
    item.className = `${classPrefix}-message${isMine ? ' is-mine' : ''}`;
    item.dataset.messageId = messageId;

    const bubble = document.createElement('div');
    bubble.className = `${classPrefix}-bubble`;

    const author = document.createElement('strong');
    author.className = `${classPrefix}-author`;
    author.textContent = isMine ? 'Bạn' : String(message.sender_name || (message.is_admin ? 'Admin' : 'Khách hàng'));

    const body = document.createElement('p');
    body.className = `${classPrefix}-body`;
    body.textContent = String(message.body ?? '');

    const time = document.createElement('time');
    time.className = `${classPrefix}-time`;
    time.dateTime = String(message.created_at || '');
    time.textContent = formatTimestamp(message.created_at);

    bubble.append(author, body, time);
    item.appendChild(bubble);
    const nextItem = Array.from(container.querySelectorAll('[data-message-id]'))
        .find((existingItem) => Number(existingItem.dataset.messageId) > Number(messageId));
    if (nextItem) {
        container.insertBefore(item, nextItem);
    } else {
        container.appendChild(item);
    }
    if (emptyElement) emptyElement.hidden = true;
    container.scrollTop = container.scrollHeight;
    return true;
}

function attachRealtime(channelName, eventName, onMessage, onError) {
    try {
        const echo = createEcho();
        const channel = echo.private(channelName);
        channel.listen(`.${eventName || REALTIME_EVENT.slice(1)}`, onMessage);
        if (typeof channel.error === 'function') channel.error(onError);
        const connection = echo.connector?.pusher?.connection;
        if (connection && typeof connection.bind === 'function') {
            connection.bind('error', onError);
            connection.bind('disconnected', onError);
            connection.bind('unavailable', onError);
            connection.bind('failed', onError);
        }
        return echo;
    } catch (error) {
        onError(error);
        return null;
    }
}

function conversationUrl(template, conversationId) {
    return template.replace('__CONVERSATION__', encodeURIComponent(String(conversationId)));
}

function initializeUserChat(root) {
    const errorElement = root.querySelector('[data-chat-error]');
    const statusElement = root.querySelector('[data-chat-status]');
    const messagesElement = root.querySelector('[data-chat-messages]');
    const emptyElement = root.querySelector('[data-chat-empty]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const submit = root.querySelector('[data-chat-submit]');
    const count = root.querySelector('[data-chat-count]');
    const unreadElement = root.querySelector('[data-chat-unread-count]');
    const currentUserId = root.dataset.chatUserId;
    const classPrefix = root.dataset.chatClassPrefix || 'chat';
    const maxLength = Number(root.dataset.maxLength || MAX_MESSAGE_LENGTH);
    const seenIds = new Set();
    let realtimeFallbackTimer = null;
    let overviewRequestInFlight = false;

    function setUnreadCount(value) {
        const unread = Math.max(0, Number.parseInt(value, 10) || 0);
        if (unreadElement) unreadElement.textContent = String(unread);
    }

    function setStatus(online) {
        statusElement.textContent = online ? 'Admin đang online' : 'Admin đang bận, sẽ trả lời sau';
        statusElement.classList.toggle('is-online', online);
        statusElement.classList.toggle('is-busy', !online);
    }

    async function refreshStatus() {
        try {
            const payload = await requestJson(root.dataset.statusUrl);
            setStatus(payload.online === true);
            clearError(errorElement);
        } catch (error) {
            setStatus(false);
            showError(errorElement, `Không thể kiểm tra trạng thái admin: ${error.message}`);
        }
    }

    async function markRead() {
        try {
            await requestJson(root.dataset.readUrl, { method: 'POST' });
            return true;
        } catch (error) {
            showError(errorElement, `Không thể cập nhật trạng thái đã đọc: ${error.message}`);
            return false;
        }
    }

    async function loadOverview() {
        if (overviewRequestInFlight) return;
        overviewRequestInFlight = true;

        try {
            const payload = await requestJson(root.dataset.overviewUrl);
            (payload.data || []).forEach((message) => appendMessage(
                messagesElement,
                emptyElement,
                seenIds,
                message,
                currentUserId,
                classPrefix,
            ));
            setUnreadCount(payload.unread);
            if (await markRead()) setUnreadCount(0);
            clearError(errorElement);
        } catch (error) {
            showError(errorElement, `Không thể tải lịch sử tin nhắn: ${error.message}`);
        } finally {
            overviewRequestInFlight = false;
        }
    }

    function startUserMessagePolling() {
        if (realtimeFallbackTimer !== null) return;
        realtimeFallbackTimer = window.setInterval(loadOverview, USER_CHAT_REFRESH_INTERVAL);
    }

    function startRealtimeFallback() {
        startUserMessagePolling();
        loadOverview();
    }

    async function sendMessage(event) {
        event.preventDefault();
        const body = input.value.trim();
        if (!body || body.length > maxLength || submit.disabled) return;

        submit.disabled = true;
        try {
            const payload = await requestJson(root.dataset.sendUrl, {
                method: 'POST',
                body: JSON.stringify({ body }),
            });
            appendMessage(messagesElement, emptyElement, seenIds, payload.data, currentUserId, classPrefix);
            input.value = '';
            setCount(input, count, maxLength);
            clearError(errorElement);
        } catch (error) {
            showError(errorElement, `Không thể gửi tin nhắn: ${error.message}`);
        } finally {
            submit.disabled = false;
        }
    }

    attachRealtime(root.dataset.channel, root.dataset.event, (message) => {
        const inserted = appendMessage(messagesElement, emptyElement, seenIds, message, currentUserId, classPrefix);
        if (inserted && String(message.sender_id) !== String(currentUserId)) {
            setUnreadCount(Number(unreadElement?.textContent || 0) + 1);
            markRead().then((marked) => {
                if (marked) setUnreadCount(0);
            });
        }
    }, (error) => {
        showError(errorElement, `Realtime chưa kết nối: ${error?.message || 'vui lòng thử lại sau.'}`);
        startRealtimeFallback();
    });
    startUserMessagePolling();
    input.addEventListener('input', () => setCount(input, count, maxLength));
    submitOnEnter(input, form);
    form.addEventListener('submit', sendMessage);
    loadOverview();
    refreshStatus();
    window.setInterval(refreshStatus, STATUS_POLL_INTERVAL);
}

function initializeAdminChat(root) {
    const errorElement = root.querySelector('[data-chat-error]');
    const listElement = root.querySelector('[data-admin-conversations]');
    const listEmptyElement = root.querySelector('[data-admin-empty]');
    const messagesElement = root.querySelector('[data-chat-messages]');
    const messageEmptyElement = root.querySelector('[data-chat-empty]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const submit = root.querySelector('[data-chat-submit]');
    const count = root.querySelector('[data-chat-count]');
    const totalUnread = root.querySelector('[data-admin-unread]');
    const selectedUser = root.querySelector('[data-admin-selected-user]');
    const selectedHint = root.querySelector('[data-admin-selected-hint]');
    const currentAdminId = root.dataset.adminId;
    const maxLength = Number(root.dataset.maxLength || MAX_MESSAGE_LENGTH);
    const seenIds = new Set();
    let selectedConversationId = null;
    let selectionVersion = 0;
    let historyController = null;
    let conversationsController = null;
    let conversationsRequestVersion = 0;
    let realtimeFallbackTimer = null;

    function setSelectedState(conversation) {
        selectedConversationId = conversation ? String(conversation.id) : null;
        if (selectedUser) selectedUser.textContent = conversation?.user_name || 'Chọn một khách hàng';
        if (selectedHint) selectedHint.textContent = conversation
            ? 'Bạn đang xem cuộc hội thoại riêng của khách hàng này.'
            : 'Những tin nhắn mới sẽ xuất hiện theo thời gian thực.';
        if (input) input.disabled = !conversation;
        if (submit) submit.disabled = !conversation;
    }

    function renderConversations(conversations) {
        if (listElement) listElement.replaceChildren();
        if (listEmptyElement) listEmptyElement.hidden = conversations.length > 0;
        conversations.forEach((conversation) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-chat-conversation';
            button.dataset.conversationId = String(conversation.id);
            if (Number(conversation.unread || 0) > 0) button.classList.add('is-unread');
            if (String(conversation.id) === selectedConversationId) button.classList.add('is-selected');

            const row = document.createElement('span');
            row.className = 'admin-chat-conversation-row';
            const name = document.createElement('span');
            name.className = 'admin-chat-conversation-name';
            name.textContent = String(conversation.user_name || 'Khách hàng');
            const unread = document.createElement('span');
            unread.className = 'admin-chat-unread';
            unread.textContent = String(conversation.unread || 0);
            unread.hidden = Number(conversation.unread || 0) < 1;
            row.append(name, unread);

            const time = document.createElement('time');
            time.className = 'admin-chat-conversation-time';
            time.textContent = formatTimestamp(conversation.last_message_at) || 'Chưa có tin nhắn';
            const preview = document.createElement('span');
            preview.className = 'admin-chat-conversation-preview';
            preview.textContent = String(conversation.last_message || '');
            button.append(row, preview, time);
            listElement.appendChild(button);
        });
        const total = conversations.reduce((sum, conversation) => sum + Number(conversation.unread || 0), 0);
        const toggleUnread = document.querySelector('[data-chat-toggle-unread]');
        if (totalUnread) totalUnread.textContent = String(total);
        if (toggleUnread) toggleUnread.textContent = String(total);
    }

    async function loadConversations() {
        conversationsRequestVersion += 1;
        const requestVersion = conversationsRequestVersion;
        if (conversationsController) conversationsController.abort();
        const controller = new AbortController();
        conversationsController = controller;

        try {
            const payload = await requestJson(root.dataset.conversationsUrl, { signal: controller.signal });
            if (controller.signal.aborted || requestVersion !== conversationsRequestVersion) return false;
            renderConversations(payload.data || []);
            clearError(errorElement);
            return true;
        } catch (error) {
            if (error.name === 'AbortError' || controller.signal.aborted || requestVersion !== conversationsRequestVersion) {
                return false;
            }
            showError(errorElement, `Không thể tải danh sách cuộc hội thoại: ${error.message}`);
            return false;
        } finally {
            if (conversationsController === controller) conversationsController = null;
        }
    }

    async function markRead(conversationId, signal) {
        try {
            await requestJson(conversationUrl(root.dataset.readTemplate, conversationId), { method: 'POST', signal });
            return true;
        } catch (error) {
            if (error.name === 'AbortError') return false;
            showError(errorElement, `Không thể cập nhật trạng thái đã đọc: ${error.message}`);
            return false;
        }
    }

    function isCurrentSelection(conversationId, version) {
        return version === selectionVersion && String(conversationId) === selectedConversationId;
    }

    async function loadSelectedHistory(conversationId, version, { replace = false, markAsRead = false } = {}) {
        if (!isCurrentSelection(conversationId, version)) return false;

        if (historyController) historyController.abort();
        const controller = new AbortController();
        historyController = controller;

        if (replace) {
            messagesElement.replaceChildren();
            messageEmptyElement.hidden = false;
            seenIds.clear();
        }

        try {
            const payload = await requestJson(
                conversationUrl(root.dataset.messagesTemplate, conversationId),
                { signal: controller.signal },
            );
            if (!isCurrentSelection(conversationId, version) || controller.signal.aborted) return false;

            (payload.data || []).forEach((message) => appendMessage(
                messagesElement,
                messageEmptyElement,
                seenIds,
                message,
                currentAdminId,
                'admin-chat',
            ));

            if (markAsRead) {
                await markRead(conversationId, controller.signal);
                if (!isCurrentSelection(conversationId, version) || controller.signal.aborted) return false;
            }

            return true;
        } catch (error) {
            if (error.name === 'AbortError' || !isCurrentSelection(conversationId, version)) return false;
            showError(errorElement, `Không thể tải cuộc hội thoại: ${error.message}`);
            return false;
        } finally {
            if (historyController === controller) historyController = null;
        }
    }

    async function selectConversation(conversation) {
        selectionVersion += 1;
        const version = selectionVersion;
        setSelectedState(conversation);
        const loaded = await loadSelectedHistory(conversation.id, version, { replace: true, markAsRead: true });
        if (loaded && isCurrentSelection(conversation.id, version)) await loadConversations();
    }

    async function refreshSelectedConversation() {
        if (!selectedConversationId || historyController) return;
        const version = selectionVersion;
        const loaded = await loadSelectedHistory(selectedConversationId, version, { markAsRead: true });
        if (loaded && isCurrentSelection(selectedConversationId, version)) await loadConversations();
    }

    async function refreshAdminChat() {
        await loadConversations();
        await refreshSelectedConversation();
    }

    function startAdminMessagePolling() {
        if (realtimeFallbackTimer !== null) return;
        realtimeFallbackTimer = window.setInterval(refreshAdminChat, ADMIN_CHAT_REFRESH_INTERVAL);
    }

    function startRealtimeFallback() {
        startAdminMessagePolling();
        refreshAdminChat();
    }

    async function sendMessage(event) {
        event.preventDefault();
        const body = input.value.trim();
        if (!selectedConversationId || !body || body.length > maxLength || submit.disabled) return;

        submit.disabled = true;
        try {
            const payload = await requestJson(conversationUrl(root.dataset.sendTemplate, selectedConversationId), {
                method: 'POST',
                body: JSON.stringify({ body }),
            });
            appendMessage(messagesElement, messageEmptyElement, seenIds, payload.data, currentAdminId, 'admin-chat');
            input.value = '';
            setCount(input, count, maxLength);
            clearError(errorElement);
            await loadConversations();
        } catch (error) {
            showError(errorElement, `Không thể gửi tin nhắn: ${error.message}`);
        } finally {
            submit.disabled = !selectedConversationId;
        }
    }

    listElement.addEventListener('click', (event) => {
        const button = event.target.closest('[data-conversation-id]');
        if (!button) return;
        const conversation = { id: button.dataset.conversationId, user_name: button.querySelector('.admin-chat-conversation-name')?.textContent };
        selectConversation(conversation);
    });
    input.addEventListener('input', () => setCount(input, count, maxLength));
    submitOnEnter(input, form);
    form.addEventListener('submit', sendMessage);
    setSelectedState(null);

    attachRealtime(root.dataset.adminChannel, root.dataset.event, (message) => {
        if (String(message.conversation_id) === selectedConversationId) {
            const inserted = appendMessage(messagesElement, messageEmptyElement, seenIds, message, currentAdminId, 'admin-chat');
            if (inserted && String(message.sender_id) !== String(currentAdminId)) markRead(selectedConversationId);
        }
        loadConversations();
    }, (error) => {
        showError(errorElement, `Realtime chưa kết nối: ${error?.message || 'vui lòng thử lại sau.'}`);
        startRealtimeFallback();
    });
    startAdminMessagePolling();
    loadConversations();
}

function initializeChat() {
    const roots = Array.from(document.querySelectorAll('[data-chat-page]'));
    if (roots.length === 0) return;

    roots.forEach((root) => {
        if (root.dataset.chatPage === 'admin') initializeAdminChat(root);
        if (root.dataset.chatPage === 'user') initializeUserChat(root);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChat, { once: true });
} else {
    initializeChat();
}
