# User-Admin Real-time Chat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a real-time, authenticated user-to-admin chat where messages persist while admin is offline and both sides can receive replies through Laravel Reverb and Echo.

**Architecture:** Store one `ChatConversation` per user and append `ChatMessage` records to it. A service owns conversation creation, message creation, unread counts, and read state; controllers expose authenticated user/admin JSON endpoints; `ChatMessageSent` broadcasts on private user/admin channels. Admin presence is a short-lived cache heartbeat, so offline users can still send and see the busy message while the admin inbox catches up on login.

**Tech Stack:** Laravel 12, PHP 8.2+, SQLite/MySQL through Eloquent, Laravel Reverb, Laravel Echo, `pusher-js`, Vite, Blade, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-09-16-user-admin-realtime-chat-design.md`

## Global Constraints

- Only authenticated users may read or send chat messages; unauthenticated visitors must still see the “Nhắn tin” link and be redirected to login when they click it.
- Each user has exactly one conversation with the admin account/group; user messages never choose a recipient.
- Persist every message before broadcasting it; a Reverb outage must not discard a saved message.
- Use private channels only: `chat.user.{userId}` and `chat.admins`.
- Use `ShouldBroadcastNow` so message delivery does not wait for the existing database queue worker.
- Trim message bodies, reject empty content, and cap content at 2,000 characters; throttle send endpoints.
- Escape message content in Blade/DOM rendering and enforce server-side ownership/admin authorization.
- Keep the test environment on `BROADCAST_CONNECTION=null`, SQLite `:memory:`, array cache, and synchronous queue.
- Do not add guest messages, group chat, attachments, message editing/deletion, or unrelated refactors.

## File Map

Create:

- `database/migrations/*_create_chat_conversations_table.php` — conversation persistence and one-conversation-per-user constraint.
- `database/migrations/*_create_chat_messages_table.php` — message persistence, sender, read state, and indexes.
- `app/Models/ChatConversation.php` — user/messages relationships and conversation casts.
- `app/Models/ChatMessage.php` — conversation/sender relationships and read timestamp cast.
- `app/Services/Chat/ChatService.php` — conversation lookup, persistence, unread/read operations, and event dispatch boundary.
- `app/Services/Chat/AdminPresenceService.php` — cache-backed admin heartbeat and online check.
- `app/Http/Controllers/ChatController.php` — user/admin page and JSON endpoint actions.
- `app/Events/ChatMessageSent.php` — private-channel broadcast event and safe payload.
- `routes/channels.php` — private channel authorization callbacks.
- `resources/views/chat/user.blade.php` — user conversation page.
- `resources/views/chat/admin.blade.php` — admin inbox/conversation page.
- `resources/js/chat.js` — Echo subscriptions, status polling, heartbeat, rendering, and send actions.
- `tests/Feature/ChatTest.php` — authenticated/authorization/persistence/unread endpoint behavior.
- `tests/Feature/ChatPresenceTest.php` — admin presence endpoint and status behavior.
- `tests/Feature/ChatUiTest.php` — guest/user/admin menu and page markers.
- `tests/Feature/ChatBroadcastingTest.php` — event channel/payload and private-channel authorization.

Modify:

- `composer.json`, `composer.lock` — add Laravel Reverb.
- `package.json`, `package-lock.json` — add Laravel Echo and `pusher-js`.
- `config/broadcasting.php`, `bootstrap/app.php` — broadcasting/Reverb setup generated or completed by Laravel’s broadcasting installer.
- `routes/web.php` — user/admin chat and presence routes.
- `.env.example` — Reverb server and Vite client variables.
- `vite.config.js` — include the chat entry if the implementation keeps it separate; otherwise import it from `resources/js/app.js`.
- `resources/js/app.js`, `resources/js/bootstrap.js` — load Echo/chat behavior and CSRF configuration.
- `resources/views/layouts/app.blade.php` — authenticated user/admin links, unread markers, admin presence marker, and CSRF meta tag.
- `resources/views/layouts/shop.blade.php` — visible guest/storefront “Nhắn tin” link pointing to the auth-protected chat route.
- `README.md` — local Reverb/Echo run instructions and required environment variables.

---

### Task 1: Install and configure the real-time transport

**Files:**
- Modify: `composer.json`, `composer.lock`, `package.json`, `package-lock.json`
- Create/Modify: `config/broadcasting.php`, `routes/channels.php`, `bootstrap/app.php`, `.env.example`
- Test: command-level verification only; no application behavior exists yet

**Interfaces:**
- Produces the Reverb broadcast connection used by `ChatMessageSent` and the Echo client used by the Blade chat pages.
- Preserves `BROADCAST_CONNECTION=null` in `phpunit.xml` so tests do not require a running WebSocket server.

- [ ] **Step 1: Install the Laravel broadcasting/Reverb scaffold**

Run:

```powershell
php artisan install:broadcasting --reverb
```

If the installer leaves the driver package absent, run:

```powershell
composer require laravel/reverb
```

- [ ] **Step 2: Install the browser client dependencies**

Run:

```powershell
npm install laravel-echo pusher-js
```

- [ ] **Step 3: Add explicit Reverb environment defaults**

Ensure `.env.example` contains values equivalent to:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local-chat
REVERB_APP_KEY=local-chat-key
REVERB_APP_SECRET=local-chat-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Do not overwrite the testing values in `phpunit.xml`.

- [ ] **Step 4: Confirm the generated broadcasting route/config is loaded**

Run:

```powershell
php artisan route:list --path=broadcasting
php artisan config:clear
```

Expected: Laravel exposes the broadcast authentication route and no configuration exception is reported.

- [ ] **Step 5: Build the current frontend before feature code**

Run:

```powershell
npm run build
```

Expected: Vite exits with code 0. If it fails because chat has not been imported yet, leave the app entry unchanged and repeat after Task 6.

- [ ] **Step 6: Record the setup checkpoint**

```powershell
git add composer.json composer.lock package.json package-lock.json config/broadcasting.php routes/channels.php bootstrap/app.php .env.example
git commit -m "chore: configure reverb broadcasting"
```

The current checkout has no Git metadata, so this command is a planned checkpoint and must be reported as unavailable if that remains true during execution.

### Task 2: Add conversation/message persistence and the service boundary

**Files:**
- Create: `database/migrations/*_create_chat_conversations_table.php`
- Create: `database/migrations/*_create_chat_messages_table.php`
- Create: `app/Models/ChatConversation.php`
- Create: `app/Models/ChatMessage.php`
- Create: `app/Services/Chat/ChatService.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/ChatTest.php`

**Interfaces:**
- `ChatService::conversationForUser(User $user): ChatConversation` returns the same conversation on repeated calls for one user.
- `ChatConversation::user(): BelongsTo` and `ChatConversation::messages(): HasMany` expose the aggregate.
- `ChatMessage::conversation(): BelongsTo` and `ChatMessage::sender(): BelongsTo` expose message ownership.

- [ ] **Step 1: Write the failing conversation uniqueness test**

Add this test before creating the migration/model/service implementation:

```php
public function test_a_user_reuses_one_conversation(): void
{
    $user = User::factory()->create(['role' => 'user']);
    $service = app(\App\Services\Chat\ChatService::class);

    $first = $service->conversationForUser($user);
    $second = $service->conversationForUser($user);

    $this->assertSame($first->id, $second->id);
    $this->assertDatabaseCount('chat_conversations', 1);
}
```

- [ ] **Step 2: Run the focused test and verify the expected RED failure**

```powershell
php artisan test tests/Feature/ChatTest.php --filter=test_a_user_reuses_one_conversation
```

Expected: failure because the chat service/tables do not exist yet, not a test syntax error.

- [ ] **Step 3: Create the conversations migration**

The migration must create:

```php
$table->id();
$table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
$table->timestamp('last_message_at')->nullable()->index();
$table->timestamps();
```

- [ ] **Step 4: Create the messages migration**

The migration must create:

```php
$table->id();
$table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
$table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
$table->text('body');
$table->timestamp('read_at')->nullable();
$table->timestamps();
$table->index(['conversation_id', 'read_at']);
```

- [ ] **Step 5: Implement the two models and User relationships**

Use `$fillable` only for the intended fields, cast `read_at` and `last_message_at` to `datetime`, and add these methods to `User`:

```php
public function chatConversation(): HasOne
{
    return $this->hasOne(ChatConversation::class);
}

public function sentChatMessages(): HasMany
{
    return $this->hasMany(ChatMessage::class, 'sender_id');
}
```

- [ ] **Step 6: Implement the minimal service method**

`conversationForUser` must use `firstOrCreate(['user_id' => $user->id])`, relying on the unique database constraint for concurrency safety.

- [ ] **Step 7: Run the focused test and verify GREEN**

```powershell
php artisan test tests/Feature/ChatTest.php --filter=test_a_user_reuses_one_conversation
```

Expected: PASS with one conversation row.

- [ ] **Step 8: Run migration/model smoke checks**

```powershell
php artisan migrate:fresh --env=testing --force
php artisan test tests/Feature/ChatTest.php --filter=test_a_user_reuses_one_conversation
```

- [ ] **Step 9: Record the persistence checkpoint**

```powershell
git add database/migrations app/Models/User.php app/Models/ChatConversation.php app/Models/ChatMessage.php app/Services/Chat/ChatService.php tests/Feature/ChatTest.php
git commit -m "feat: add chat persistence models"
```

### Task 3: Implement private broadcast channels and message event contract

**Files:**
- Create: `app/Events/ChatMessageSent.php`
- Modify: `routes/channels.php`
- Create/Modify: `tests/Feature/ChatBroadcastingTest.php`

**Interfaces:**
- `ChatMessageSent` implements `ShouldBroadcastNow`.
- `ChatMessageSent::broadcastOn(): array` returns `PrivateChannel('chat.user.{userId}')` and `PrivateChannel('chat.admins')`.
- `ChatMessageSent::broadcastAs(): string` returns `chat.message.sent`.
- `ChatMessageSent::broadcastWith(): array` returns only `id`, `conversation_id`, `sender_id`, `sender_name`, `body`, `created_at`, and `is_admin`.

- [ ] **Step 1: Write the failing event contract test**

```php
public function test_message_event_broadcasts_safe_payload_to_user_and_admin_channels(): void
{
    $user = User::factory()->create(['role' => 'user', 'name' => 'Customer']);
    $message = $user->chatConversation()->create([
        'last_message_at' => now(),
    ])->messages()->create([
        'sender_id' => $user->id,
        'body' => 'Tôi cần tư vấn size.',
    ]);

    $event = new \App\Events\ChatMessageSent($message->load('conversation', 'sender'));
    $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

    $this->assertSame(['private-chat.user.'.$user->id, 'private-chat.admins'], $channels);
    $this->assertSame('chat.message.sent', $event->broadcastAs());
    $this->assertSame('Tôi cần tư vấn size.', $event->broadcastWith()['body']);
    $this->assertArrayNotHasKey('password', $event->broadcastWith());
}
```

- [ ] **Step 2: Run the focused test and verify RED**

```powershell
php artisan test tests/Feature/ChatBroadcastingTest.php --filter=test_message_event_broadcasts_safe_payload_to_user_and_admin_channels
```

Expected: failure because the event does not exist yet.

- [ ] **Step 3: Implement `ChatMessageSent`**

Use the loaded `ChatMessage` model, `ShouldBroadcastNow`, two `PrivateChannel` instances, the `chat.message.sent` alias, and a scalar-safe `broadcastWith` payload. Format `created_at` with `toIso8601String()`.

- [ ] **Step 4: Add private-channel authorization**

Use callbacks equivalent to:

```php
Broadcast::channel('chat.user.{userId}', function (User $user, int $userId): bool {
    return $user->isAdmin() || $user->id === $userId;
});

Broadcast::channel('chat.admins', function (User $user): bool {
    return $user->isAdmin();
});
```

- [ ] **Step 5: Add authorization regression tests**

```php
public function test_a_user_cannot_authorize_another_users_private_channel(): void
{
    $user = User::factory()->create(['role' => 'user']);
    $other = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-chat.user.'.$other->id,
        ])
        ->assertForbidden();
}

public function test_only_admin_can_authorize_the_admin_channel(): void
{
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '123.456', 'channel_name' => 'private-chat.admins',
    ])->assertForbidden();

    $this->actingAs($admin)->postJson('/broadcasting/auth', [
        'socket_id' => '123.456', 'channel_name' => 'private-chat.admins',
    ])->assertOk();
}
```

- [ ] **Step 6: Run the broadcasting tests and verify GREEN**

```powershell
php artisan test tests/Feature/ChatBroadcastingTest.php
```

- [ ] **Step 7: Record the broadcast checkpoint**

```powershell
git add app/Events/ChatMessageSent.php routes/channels.php tests/Feature/ChatBroadcastingTest.php
git commit -m "feat: add private chat broadcasting"
```

### Task 4: Build authenticated user/admin chat endpoints

**Files:**
- Modify: `app/Services/Chat/ChatService.php`
- Create: `app/Http/Controllers/ChatController.php`
- Modify: `routes/web.php`
- Create/Modify: `tests/Feature/ChatTest.php`

**Interfaces:**
- `ChatService::sendUserMessage(User $user, string $body): ChatMessage` creates/reuses that user’s conversation, updates `last_message_at`, then dispatches `ChatMessageSent` after persistence.
- `ChatService::sendAdminMessage(User $admin, ChatConversation $conversation, string $body): ChatMessage` requires the caller to be an admin at the controller boundary and dispatches the same event.
- `ChatService::markUserMessagesRead(User $user): int` marks admin-sent messages in that user’s conversation.
- `ChatService::markAdminMessagesRead(ChatConversation $conversation): int` marks user-sent messages in the selected conversation.
- `ChatService::unreadForUser(User $user): int` and `unreadForAdmins(): int` return unread counts.
- JSON message objects use the exact keys from Task 3.

- [ ] **Step 1: Write the failing user endpoint tests**

```php
public function test_authenticated_user_can_send_and_reuse_their_conversation(): void
{
    Event::fake([\App\Events\ChatMessageSent::class]);
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->postJson(route('chat.user.messages.store'), [
        'body' => 'Shop còn size M không?',
    ])->assertCreated()->assertJsonPath('data.body', 'Shop còn size M không?');

    $this->actingAs($user)->postJson(route('chat.user.messages.store'), [
        'body' => 'Mình muốn hỏi thêm màu.',
    ])->assertCreated();

    $this->assertDatabaseCount('chat_conversations', 1);
    $this->assertDatabaseCount('chat_messages', 2);
    Event::assertDispatchedTimes(\App\Events\ChatMessageSent::class, 2);
}

public function test_guest_is_redirected_when_opening_chat(): void
{
    $this->get(route('chat.user.index'))
        ->assertRedirect(route('login'));
}

public function test_message_validation_rejects_empty_and_overlong_body(): void
{
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->postJson(route('chat.user.messages.store'), ['body' => '   '])
        ->assertUnprocessable()->assertJsonValidationErrors('body');

    $this->actingAs($user)->postJson(route('chat.user.messages.store'), ['body' => str_repeat('a', 2001)])
        ->assertUnprocessable()->assertJsonValidationErrors('body');
}
```

- [ ] **Step 2: Run the user endpoint tests and verify RED**

```powershell
php artisan test tests/Feature/ChatTest.php --filter='authenticated_user|guest_is_redirected|message_validation'
```

Expected: route/controller/service failures because the endpoints are not implemented.

- [ ] **Step 3: Write the failing admin endpoint tests**

```php
public function test_admin_can_list_unread_conversations_and_reply(): void
{
    Event::fake([\App\Events\ChatMessageSent::class]);
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user', 'name' => 'Nguyen An']);

    $this->actingAs($user)->postJson(route('chat.user.messages.store'), ['body' => 'Xin chào shop.']);

    $this->actingAs($admin)->get(route('chat.admin.index'))
        ->assertOk()->assertSee('Nguyen An')->assertSee('1');

    $conversation = $user->chatConversation()->firstOrFail();
    $this->actingAs($admin)->postJson(route('chat.admin.messages.store', $conversation), [
        'body' => 'Shop chào bạn, mình hỗ trợ ngay đây ạ.',
    ])->assertCreated();

    $this->assertDatabaseHas('chat_messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $admin->id,
        'body' => 'Shop chào bạn, mình hỗ trợ ngay đây ạ.',
    ]);
}

public function test_regular_user_cannot_access_admin_chat_endpoints(): void
{
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->get(route('chat.admin.index'))->assertForbidden();
    $this->actingAs($user)->postJson(route('chat.admin.presence'))->assertForbidden();
}
```

- [ ] **Step 4: Run the admin tests and verify RED**

```powershell
php artisan test tests/Feature/ChatTest.php --filter='admin_can_list|regular_user_cannot'
```

- [ ] **Step 5: Implement `ChatService` message/read/count methods**

Use `DB::transaction` to create the message and update `last_message_at`; dispatch `ChatMessageSent` only after the transaction returns. Query unread user messages with `sender_id !== conversation.user_id` for the user side and `sender_id === conversation.user_id` for the admin side. Always return models loaded with `conversation` and `sender`.

- [ ] **Step 6: Implement `ChatController` page and JSON actions**

Use `auth` route middleware for user actions and `admin` middleware for admin actions. Validate bodies with `required|string|max:2000`, call `trim()` before passing to the service, and return HTTP 201 with `{data: ...}` for successful sends. Return 403/404 through normal Laravel authorization when a user attempts another conversation.

- [ ] **Step 7: Register routes in `routes/web.php`**

Register the user route outside the `auth` group only for link generation? The actual `GET /nhan-tin`, message, read, and status routes must be inside `Route::middleware('auth')`. Register admin routes inside `Route::prefix('admin')->middleware(['auth', 'admin'])`, placing the fixed `/nhan-tin/presence` route before `{conversation}`.

Use these names:

```php
chat.user.index
chat.user.messages
chat.user.messages.store
chat.user.read
chat.user.admin-status
chat.admin.index
chat.admin.messages
chat.admin.messages.store
chat.admin.read
chat.admin.presence
```

- [ ] **Step 8: Run the full backend chat tests and verify GREEN**

```powershell
php artisan test tests/Feature/ChatTest.php
```

- [ ] **Step 9: Record the endpoint checkpoint**

```powershell
git add app/Services/Chat/ChatService.php app/Http/Controllers/ChatController.php routes/web.php tests/Feature/ChatTest.php
git commit -m "feat: add user and admin chat endpoints"
```

### Task 5: Add admin presence and offline/busy behavior

**Files:**
- Create: `app/Services/Chat/AdminPresenceService.php`
- Modify: `app/Http/Controllers/ChatController.php`, `routes/web.php`
- Create/Modify: `tests/Feature/ChatPresenceTest.php`

**Interfaces:**
- `AdminPresenceService::touch(User $admin): void` writes `chat.admin.presence.{adminId}` for 75 seconds.
- `AdminPresenceService::isAnyAdminOnline(): bool` returns true when at least one admin presence key remains valid.
- `ChatController::adminPresence()` returns `{online:true}` for a valid admin heartbeat.
- `ChatController::adminStatus()` returns `{online:true}` or `{online:false}` for an authenticated user.

- [ ] **Step 1: Write the failing presence tests**

```php
public function test_user_sees_admin_busy_without_a_recent_heartbeat(): void
{
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->getJson(route('chat.user.admin-status'))
        ->assertOk()->assertJson(['online' => false]);
}

public function test_admin_heartbeat_makes_admin_online_for_users(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin)->postJson(route('chat.admin.presence'))
        ->assertOk()->assertJson(['online' => true]);

    $this->actingAs($user)->getJson(route('chat.user.admin-status'))
        ->assertOk()->assertJson(['online' => true]);
}

public function test_presence_expires_after_the_ttl(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin)->postJson(route('chat.admin.presence'));
    $this->travel(76)->seconds();

    $this->actingAs($user)->getJson(route('chat.user.admin-status'))
        ->assertOk()->assertJson(['online' => false]);
}
```

- [ ] **Step 2: Run the focused presence tests and verify RED**

```powershell
php artisan test tests/Feature/ChatPresenceTest.php
```

- [ ] **Step 3: Implement the cache service and controller actions**

Use the configured cache store, key prefix `chat.admin.presence.`, and `now()->addSeconds(75)`. `isAnyAdminOnline()` may inspect `User::where('role', 'admin')->pluck('id')` and return true on the first valid cache key; it must return false when no admin exists.

- [ ] **Step 4: Run presence tests and verify GREEN**

```powershell
php artisan test tests/Feature/ChatPresenceTest.php
```

- [ ] **Step 5: Record the presence checkpoint**

```powershell
git add app/Services/Chat/AdminPresenceService.php app/Http/Controllers/ChatController.php routes/web.php tests/Feature/ChatPresenceTest.php
git commit -m "feat: add admin chat presence"
```

### Task 6: Add guest-visible navigation and real-time Blade/JavaScript UI

**Files:**
- Create: `resources/views/chat/user.blade.php`, `resources/views/chat/admin.blade.php`, `resources/js/chat.js`
- Modify: `resources/views/layouts/app.blade.php`, `resources/views/layouts/shop.blade.php`, `resources/js/app.js`, `resources/js/bootstrap.js`, `vite.config.js`
- Create/Modify: `tests/Feature/ChatUiTest.php`

**Interfaces:**
- User page root has `data-chat-page="user"`, `data-chat-user-id`, and URLs for messages/status/read/send.
- Admin page root has `data-chat-page="admin"`, selected conversation metadata, and URLs for inbox/messages/read/send/presence.
- Broadcast event name consumed by Echo is `.chat.message.sent`.
- User navigation link always uses `route('chat.user.index')`; auth middleware performs the guest redirect.

- [ ] **Step 1: Write the failing UI/navigation tests**

```php
public function test_guest_storefront_shows_chat_link_that_requires_login(): void
{
    $this->get(route('shop.home'))
        ->assertOk()
        ->assertSee('href="'.route('chat.user.index').'"', false)
        ->assertSee('Nhắn tin');

    $this->get(route('chat.user.index'))->assertRedirect(route('login'));
}

public function test_user_and_admin_pages_expose_chat_roots(): void
{
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)->get(route('chat.user.index'))
        ->assertOk()->assertSee('data-chat-page="user"', false);

    $this->actingAs($admin)->get(route('chat.admin.index'))
        ->assertOk()->assertSee('data-chat-page="admin"', false);
}
```

- [ ] **Step 2: Run the focused UI tests and verify RED**

```powershell
php artisan test tests/Feature/ChatUiTest.php
```

- [ ] **Step 3: Implement the user Blade page**

Render messages from server-provided JSON or the user messages endpoint, an empty state, online/busy status, a textarea with `maxlength="2000"`, send button, validation/error area, and an unread badge. Use Blade escaping for initial body text.

- [ ] **Step 4: Implement the admin Blade page**

Render the conversation list, unread counts, selected conversation, message history, reply form, and empty inbox state. Do not expose a user’s conversation in the initial view unless the controller selected it from the admin-authorized list.

- [ ] **Step 5: Add links to both layouts**

In `layouts.shop`, add a visible “Nhắn tin” link for guest and authenticated visitors. In `layouts.app`, add the user sidebar item in the user branch and an admin sidebar/header item in the admin branch. Use `Request::routeIs('chat.*')`/the corresponding admin route names for active state and display unread counts only when `auth()->check()`.

- [ ] **Step 6: Initialize Echo and CSRF in the frontend**

In `resources/js/bootstrap.js`, keep Axios setup and add the `X-CSRF-TOKEN` header from a `<meta name="csrf-token">` tag. In `resources/js/chat.js`, configure Echo with:

```js
window.Pusher = Pusher;
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Create the Echo instance only when the page has a chat/presence data marker, subscribe to `private('chat.user.' + userId)` or `private('chat.admins')`, and listen for `.chat.message.sent`.

- [ ] **Step 7: Implement user real-time behavior**

Use `fetch`/Axios POST with CSRF for sends, append messages by id to prevent duplicate render, poll `chat.user.admin-status` every 30 seconds, and render “Admin đang online” or “Admin đang bận, sẽ trả lời sau”. On a Reverb connection error, keep the saved message visible and show a non-blocking “Kết nối real-time đang gián đoạn” notice.

- [ ] **Step 8: Implement admin real-time behavior**

Send a presence heartbeat immediately and every 30 seconds from the admin layout marker, subscribe to `private-chat.admins`, update or insert the matching conversation in last-message order, increment unread for conversations not selected, and load/mark read when a conversation is selected.

- [ ] **Step 9: Import chat behavior into the Vite entry and build**

Import `./chat` from `resources/js/app.js` (or include a separate entry in `vite.config.js`, but do not initialize a WebSocket connection on unrelated pages). Add CSRF meta tags to both layouts.

Run:

```powershell
npm run build
php artisan test tests/Feature/ChatUiTest.php
```

Expected: Vite and UI tests pass.

- [ ] **Step 10: Record the UI checkpoint**

```powershell
git add resources/views/chat resources/views/layouts/app.blade.php resources/views/layouts/shop.blade.php resources/js/app.js resources/js/bootstrap.js resources/js/chat.js vite.config.js tests/Feature/ChatUiTest.php
git commit -m "feat: add real-time chat interface"
```

### Task 7: Document operation and run the complete verification matrix

**Files:**
- Modify: `README.md`
- Verify: all changed application/test files

**Interfaces:**
- README explains the exact commands needed for PHP, Reverb, queue-independent broadcast, and Vite development.

- [ ] **Step 1: Document local real-time startup**

Add a concise section to `README.md` with:

```powershell
php artisan serve
php artisan reverb:start
npm run dev
```

Explain that the browser-facing `VITE_REVERB_*` values must match the Reverb host/port and that tests intentionally use `BROADCAST_CONNECTION=null`.

- [ ] **Step 2: Run the complete PHP test suite**

```powershell
php artisan test
```

Expected: exit code 0 and zero failures/errors.

- [ ] **Step 3: Run the production frontend build**

```powershell
npm run build
```

Expected: exit code 0 with generated Vite assets.

- [ ] **Step 4: Verify route and migration state**

```powershell
php artisan route:list --path=nhan-tin
php artisan route:list --path=admin/nhan-tin
php artisan migrate:status
```

Confirm all named user/admin chat routes exist, the fixed presence route is not captured by `{conversation}`, and both chat migrations are in the migrated state.

- [ ] **Step 5: Perform the two-session real-time smoke test**

Start the documented PHP/Reverb/Vite processes. In one authenticated browser session use a user account; in another use an admin account. Verify:

1. A guest sees “Nhắn tin” and is redirected to login on click.
2. User sends while admin process/page is offline; the user sees the busy text and the message remains after refresh.
3. Admin logs in and sees the conversation/unread badge.
4. Admin replies and the user sees it without refresh.
5. User/admin unread counts clear when the selected conversation is opened.

- [ ] **Step 6: Re-read the spec against the implementation**

Check every acceptance criterion in `docs/superpowers/specs/2026-09-16-user-admin-realtime-chat-design.md`, especially guest-visible navigation, offline persistence, private-channel authorization, presence TTL, and no cross-user access.

- [ ] **Step 7: Record the final checkpoint**

```powershell
git add README.md
git commit -m "docs: document real-time chat setup"
```

If Git metadata is still unavailable, report the exact verification output and the uncommitted working-tree state instead of claiming a commit was created.

## Plan Self-Review

- Spec coverage: persistence is Task 2; event/private channels are Task 3; authenticated user/admin flows are Task 4; busy/online presence is Task 5; both guest-visible and authenticated layouts plus Echo UI are Task 6; operation and acceptance verification are Task 7.
- Placeholder scan: every implementation step names files, interfaces, validation, or concrete commands; no step is left as an unspecified future action.
- Type consistency: all later tasks use `ChatService`, `ChatConversation`, `ChatMessageSent`, `chat.message.sent`, the same route names, the same payload keys, and the same private channel names established earlier.
- Scope check: all tasks belong to one user-admin chat subsystem and produce an independently testable increment; no unrelated application refactor is included.
