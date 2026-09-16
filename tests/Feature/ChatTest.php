<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Events\ChatMessageSent;
use App\Services\Chat\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_reuses_one_conversation(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $service = app(ChatService::class);

        $first = $service->conversationForUser($user);
        $second = $service->conversationForUser($user);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('chat_conversations', 1);
    }

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

    public function test_user_and_admin_messages_still_return_success_when_broadcasting_fails(): void
    {
        Event::listen(ChatMessageSent::class, static function (): void {
            throw new \RuntimeException('Reverb unavailable');
        });

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson(route('chat.user.messages.store'), [
            'body' => 'Tin van duoc luu khi Reverb loi.',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Tin van duoc luu khi Reverb loi.');

        $conversation = $user->chatConversation()->firstOrFail();

        $this->actingAs($admin)->postJson(route('chat.admin.messages.store', $conversation), [
            'body' => 'Admin van tra loi khi Reverb loi.',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Admin van tra loi khi Reverb loi.');

        $this->assertDatabaseCount('chat_messages', 2);
    }

    public function test_admin_conversation_list_contains_the_latest_message_preview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'name' => 'Preview User']);
        $conversation = $user->chatConversation()->create();
        $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => '<strong>Untrusted preview</strong>',
        ]);

        $this->actingAs($admin)->getJson(route('chat.admin.conversations'))
            ->assertOk()
            ->assertJsonPath('data.0.user_name', 'Preview User')
            ->assertJsonPath('data.0.last_message', '<strong>Untrusted preview</strong>');
    }

    public function test_guest_is_redirected_when_opening_chat(): void
    {
        $this->get(route('chat.user.index'))->assertRedirect(route('login'));
    }

    public function test_message_validation_rejects_empty_and_overlong_body(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson(route('chat.user.messages.store'), ['body' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs($user)->postJson(route('chat.user.messages.store'), ['body' => str_repeat('a', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_user_cannot_read_or_send_to_another_users_conversation(): void
    {
        $first = User::factory()->create(['role' => 'user']);
        $second = User::factory()->create(['role' => 'user']);
        $conversation = $second->chatConversation()->create();
        $conversation->messages()->create([
            'sender_id' => $second->id,
            'body' => 'Private message for the second user',
        ]);

        $this->actingAs($first)->getJson(route('chat.user.messages'))
            ->assertOk()
            ->assertJsonMissing(['body' => 'Private message for the second user']);
        $this->actingAs($first)->postJson(route('chat.user.messages.store'), ['body' => 'Không được phép'])
            ->assertCreated();
        $this->assertDatabaseMissing('chat_messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Không được phép',
        ]);
    }

    public function test_admin_can_list_unread_conversations_and_reply(): void
    {
        Event::fake([\App\Events\ChatMessageSent::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'name' => 'Nguyen An']);

        $this->actingAs($user)->postJson(route('chat.user.messages.store'), ['body' => 'Xin chào shop.']);

        $this->actingAs($admin)->getJson(route('chat.admin.conversations'))
            ->assertOk()
            ->assertJsonPath('data.0.user_name', 'Nguyen An')
            ->assertJsonPath('data.0.unread', 1)
            ->assertJsonPath('unread', 1);

        $conversation = $user->chatConversation()->firstOrFail();
        $this->actingAs($admin)->postJson(route('chat.admin.messages.store', $conversation), [
            'body' => '  Shop chào bạn, mình hỗ trợ ngay đây ạ.  ',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Shop chào bạn, mình hỗ trợ ngay đây ạ.');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'body' => 'Shop chào bạn, mình hỗ trợ ngay đây ạ.',
        ]);
        $conversation->refresh();
        $this->assertNotNull($conversation->last_message_at);

        $this->actingAs($admin)->getJson(route('chat.admin.conversations'))
            ->assertJsonPath('data.0.unread', 1)
            ->assertJsonPath('unread', 1);
    }

    public function test_regular_user_cannot_access_admin_chat_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $conversation = $user->chatConversation()->create();

        $this->actingAs($user)->get(route('chat.admin.index'))->assertForbidden();
        $this->actingAs($user)->get(route('chat.admin.messages', $conversation))->assertForbidden();
        $this->actingAs($user)->postJson(route('chat.admin.messages.store', $conversation), [
            'body' => 'No access',
        ])->assertForbidden();
        $this->actingAs($user)->postJson(route('chat.admin.read', $conversation))->assertForbidden();
        $this->actingAs($user)->postJson(route('chat.admin.presence'))->assertForbidden();
    }

    public function test_user_and_admin_read_endpoints_clear_unread_messages(): void
    {
        Event::fake([\App\Events\ChatMessageSent::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson(route('chat.user.messages.store'), ['body' => 'Xin chào shop.'])
            ->assertCreated();
        $conversation = $user->chatConversation()->firstOrFail();

        $this->actingAs($admin)->getJson(route('chat.admin.conversations'))
            ->assertJsonPath('data.0.unread', 1);
        $this->actingAs($admin)->postJson(route('chat.admin.read', $conversation))
            ->assertOk()->assertJsonPath('data.marked', 1);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
        ]);
        $this->assertNotNull($conversation->messages()->firstOrFail()->read_at);

        $this->actingAs($admin)->postJson(route('chat.admin.messages.store', $conversation), [
            'body' => 'Shop chào bạn.',
        ])->assertCreated();
        $this->actingAs($user)->getJson(route('chat.user.messages'))
            ->assertJsonPath('data.1.body', 'Shop chào bạn.');
        $this->actingAs($user)->getJson(route('chat.user.overview'))
            ->assertJsonPath('unread', 1);
        $this->actingAs($user)->postJson(route('chat.user.read'))
            ->assertOk()->assertJsonPath('data.marked', 1);
        $this->actingAs($user)->getJson(route('chat.user.overview'))
            ->assertJsonPath('unread', 0);
    }
}
