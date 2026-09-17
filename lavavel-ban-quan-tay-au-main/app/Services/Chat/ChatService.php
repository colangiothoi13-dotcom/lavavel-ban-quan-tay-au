<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChatService
{
    public function conversationForUser(User $user): ChatConversation
    {
        return ChatConversation::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    public function sendUserMessage(User $user, string $body): ChatMessage
    {
        $message = DB::transaction(function () use ($user, $body): ChatMessage {
            $conversation = $this->conversationForUser($user);
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $body,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message->load('conversation', 'sender');
        });

        $this->broadcast($message);

        return $message;
    }

    public function sendAdminMessage(User $admin, ChatConversation $conversation, string $body): ChatMessage
    {
        $message = DB::transaction(function () use ($admin, $conversation, $body): ChatMessage {
            $message = $conversation->messages()->create([
                'sender_id' => $admin->id,
                'body' => $body,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message->load('conversation', 'sender');
        });

        $this->broadcast($message);

        return $message;
    }

    public function markUserMessagesRead(User $user): int
    {
        $conversation = $user->chatConversation;

        if ($conversation === null) {
            return 0;
        }

        return $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '<>', $user->id)
            ->update(['read_at' => now()]);
    }

    public function markAdminMessagesRead(ChatConversation $conversation): int
    {
        return $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', $conversation->user_id)
            ->update(['read_at' => now()]);
    }

    public function unreadForUser(User $user): int
    {
        $conversation = $user->chatConversation;

        if ($conversation === null) {
            return 0;
        }

        return $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '<>', $user->id)
            ->count();
    }

    public function unreadForAdmins(): int
    {
        return ChatMessage::query()
            ->whereNull('read_at')
            ->whereHas('conversation', function ($query): void {
                $query->whereColumn('chat_messages.sender_id', 'chat_conversations.user_id')
                    ->whereHas('user', fn ($query) => $query->where('role', 'user'));
            })
            ->count();
    }

    private function broadcast(ChatMessage $message): void
    {
        try {
            event(new ChatMessageSent($message));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
