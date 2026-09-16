<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class ChatBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_event_broadcasts_safe_payload_to_user_and_admin_channels(): void
    {
        $user = User::factory()->create(['role' => 'user', 'name' => 'Customer']);
        $conversation = $user->chatConversation()->create(['last_message_at' => now()]);
        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => 'Tôi cần tư vấn size.',
        ]);

        $event = new \App\Events\ChatMessageSent($message->load('conversation', 'sender'));
        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();
        $payload = $event->broadcastWith();

        $this->assertSame(['private-chat.user.'.$user->id, 'private-chat.admins'], $channels);
        $this->assertSame('chat.message.sent', $event->broadcastAs());
        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame([
            'id',
            'conversation_id',
            'sender_id',
            'sender_name',
            'body',
            'created_at',
            'is_admin',
        ], array_keys($payload));
        $this->assertSame($message->created_at->toIso8601String(), $payload['created_at']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',
            $payload['created_at']
        );
        $this->assertFalse($payload['is_admin']);
        $this->assertSame('Tôi cần tư vấn size.', $event->broadcastWith()['body']);
        $this->assertArrayNotHasKey('password', $event->broadcastWith());
    }

    public function test_a_user_cannot_authorize_another_users_private_channel(): void
    {
        $this->useSignerBackedBroadcaster();

        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-chat.user.'.$other->id,
            ])
            ->assertForbidden();
    }

    public function test_matching_user_and_admin_can_authorize_a_private_user_channel(): void
    {
        $this->useSignerBackedBroadcaster();

        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = [
            'socket_id' => '123.456',
            'channel_name' => 'private-chat.user.'.$user->id,
        ];

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', $request)
            ->assertOk();

        $this->actingAs($admin)
            ->postJson('/broadcasting/auth', $request)
            ->assertOk();
    }

    public function test_only_admin_can_authorize_the_admin_channel(): void
    {
        $this->useSignerBackedBroadcaster();

        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456', 'channel_name' => 'private-chat.admins',
        ])->assertForbidden();

        $this->actingAs($admin)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456', 'channel_name' => 'private-chat.admins',
        ])->assertOk();
    }

    private function useSignerBackedBroadcaster(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => [],
            'client_options' => [],
        ]);

        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }
}
