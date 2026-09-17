<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_only_the_popup_chat_widget_on_the_storefront(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('href="'.route('chat.user.index').'"', false)
            ->assertSee('id="user-chat-toggle"', false)
            ->assertSee('id="user-chat-popup"', false);
    }

    public function test_guest_does_not_see_the_user_chat_popup_on_the_storefront(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('id="user-chat-toggle"', false)
            ->assertDontSee('id="user-chat-popup"', false);
    }

    public function test_authenticated_user_popup_loads_the_chat_bundle_on_the_storefront(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $content = $this->actingAs($user)
            ->get(route('shop.home'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            preg_match('/<script type="module"[^>]+src="[^"]*app-[^"]+\.js"/s', $content),
            'The user popup needs the compiled chat bundle to send and receive messages.'
        );
    }

    public function test_guest_is_sent_to_login_when_opening_the_chat_page(): void
    {
        $this->get(route('chat.user.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_gets_the_user_chat_page_configuration(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('chat.user.index'))
            ->assertOk()
            ->assertSee('data-chat-page="user"', false)
            ->assertSee('data-chat-user-id="'.$user->id.'"', false)
            ->assertSee('data-messages-url="'.route('chat.user.messages').'"', false)
            ->assertSee('data-overview-url="'.route('chat.user.overview').'"', false)
            ->assertSee('data-chat-unread-count', false)
            ->assertSee('data-send-url="'.route('chat.user.messages.store').'"', false)
            ->assertSee('data-status-url="'.route('chat.user.admin-status').'"', false)
            ->assertSee('chat.user.'.$user->id, false)
            ->assertSee('chat-panel--white', false);
    }

    public function test_user_chat_uses_white_incoming_blue_outgoing_bubbles_and_send_actions(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $chatPage = $this->actingAs($user)
            ->get(route('chat.user.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match('/\.chat-messages\s*\{[^}]*background:\s*#f8fafc;/s', $chatPage));
        $this->assertSame(1, preg_match('/\.chat-bubble\s*\{[^}]*background:\s*#fff;/s', $chatPage));
        $this->assertSame(1, preg_match('/\.chat-message\.is-mine \.chat-bubble\s*\{[^}]*background:\s*#2563eb;/s', $chatPage));
        $this->assertSame(1, preg_match('/\.chat-compose button\s*\{[^}]*background:\s*#2563eb;/s', $chatPage));

        $storefront = $this->actingAs($user)
            ->get(route('shop.home'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match('/\.user-chat-messages\s*\{[^}]*background:\s*#f8fafc;/s', $storefront));
        $this->assertSame(1, preg_match('/\.user-chat-message\.is-mine \.user-chat-bubble\s*\{[^}]*background:\s*#2563eb;/s', $storefront));
        $this->assertSame(1, preg_match('/\.user-chat-compose button\s*\{[^}]*background:\s*#2563eb;/s', $storefront));
    }

    public function test_user_popup_uses_the_class_prefix_expected_by_its_bubble_styles(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('shop.home'))
            ->assertOk()
            ->assertSee('data-chat-class-prefix="user-chat"', false);

        $script = file_get_contents(resource_path('js/chat.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString(
            'const classPrefix = root.dataset.chatClassPrefix || \'chat\';',
            $script
        );
    }

    public function test_admin_layout_exposes_presence_heartbeat_on_every_admin_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-presence-heartbeat', false)
            ->assertSee(route('chat.admin.presence'), false)
            ->assertSee('setInterval(sendAdminPresence, 30000)', false)
            ->assertSee('id="chat-popup"', false)
            ->assertSee('id="chat-toggle"', false);
    }

    public function test_authenticated_admin_gets_the_admin_chat_page_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('chat.admin.index'))
            ->assertOk()
            ->assertSee('data-chat-page="admin"', false)
            ->assertSee('data-conversations-url="'.route('chat.admin.conversations').'"', false)
            ->assertSee('data-presence-url="'.route('chat.admin.presence').'"', false)
            ->assertSee('data-admin-channel="chat.admins"', false)
            ->assertSee('data-messages-template="'.url('/admin/nhan-tin/__CONVERSATION__/messages').'"', false);
    }

    public function test_admin_layout_exposes_global_chat_popup_widget(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="chat-toggle"', false)
            ->assertSee('id="chat-popup"', false)
            ->assertSee('id="chat-close"', false)
            ->assertSee('id="chat-input"', false)
            ->assertSee('id="send-btn"', false)
            ->assertSee('data-chat-toggle-unread', false)
            ->assertSee('data-admin-unread', false);
    }

    public function test_regular_user_cannot_open_the_admin_chat_page_or_api(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('chat.admin.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('chat.admin.conversations'))->assertForbidden();
    }

    public function test_chat_index_json_contracts_are_available_under_api_routes(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        ChatConversation::query()->create(['user_id' => $user->id]);

        $this->actingAs($user)->getJson(route('chat.user.overview'))
            ->assertOk()
            ->assertJsonStructure(['data', 'unread']);

        $this->actingAs($admin)->getJson(route('chat.admin.conversations'))
            ->assertOk()
            ->assertJsonStructure(['data', 'unread']);
    }

    public function test_chat_script_contains_unread_race_guard_and_realtime_fallback_contracts(): void
    {
        $script = file_get_contents(resource_path('js/chat.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('setUnreadCount(payload.unread)', $script);
        $this->assertStringContainsString('AbortController', $script);
        $this->assertStringContainsString('REALTIME_FALLBACK_INTERVAL', $script);
        $this->assertStringContainsString('startRealtimeFallback', $script);
        $this->assertStringContainsString('connection.bind(\'disconnected\'', $script);
        $this->assertStringContainsString('insertBefore', $script);
        $this->assertStringContainsString('Number(existingItem.dataset.messageId)', $script);
        $this->assertStringContainsString('preview.textContent = String(conversation.last_message || \'\')', $script);

        $realtimePosition = strpos($script, '    attachRealtime(root.dataset.channel');
        $initialHistoryPosition = strrpos($script, '    loadOverview();');
        $adminRealtimePosition = strpos($script, '    attachRealtime(root.dataset.adminChannel');
        $adminInitialConversationsPosition = strrpos($script, '    loadConversations();');

        $this->assertNotFalse($realtimePosition);
        $this->assertNotFalse($initialHistoryPosition);
        $this->assertLessThan($initialHistoryPosition, $realtimePosition);
        $this->assertNotFalse($adminRealtimePosition);
        $this->assertNotFalse($adminInitialConversationsPosition);
        $this->assertLessThan($adminInitialConversationsPosition, $adminRealtimePosition);
    }
}
