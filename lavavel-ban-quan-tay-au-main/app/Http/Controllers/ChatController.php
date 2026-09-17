<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\AdminPresenceService;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly AdminPresenceService $adminPresenceService,
    )
    {
    }

    public function userIndex(Request $request): View
    {
        return view('chat.user', [
            'user' => $this->userFromRequest($request),
        ]);
    }

    public function userOverview(Request $request): JsonResponse
    {
        $user = $this->userFromRequest($request);
        $conversation = $this->chatService->conversationForUser($user);

        return response()->json([
            'data' => $this->messagePayloads($conversation),
            'unread' => $this->chatService->unreadForUser($user),
        ]);
    }

    public function userMessages(Request $request): JsonResponse
    {
        $user = $this->userFromRequest($request);
        $conversation = $this->chatService->conversationForUser($user);

        return response()->json([
            'data' => $this->messagePayloads($conversation),
        ]);
    }

    public function userSend(Request $request): JsonResponse
    {
        $user = $this->userFromRequest($request);
        $message = $this->chatService->sendUserMessage($user, $this->validatedBody($request));

        return response()->json(['data' => $this->messagePayload($message)], 201);
    }

    public function userRead(Request $request): JsonResponse
    {
        $user = $this->userFromRequest($request);

        return response()->json([
            'data' => ['marked' => $this->chatService->markUserMessagesRead($user)],
        ]);
    }

    public function userAdminStatus(): JsonResponse
    {
        return response()->json(['online' => $this->adminPresenceService->isAnyAdminOnline()]);
    }

    public function adminIndex(Request $request): View
    {
        return view('chat.admin', [
            'admin' => $this->userFromRequest($request),
        ]);
    }

    public function adminConversations(): JsonResponse
    {
        $conversations = ChatConversation::query()
            ->with(['user', 'latestMessage'])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->whereNull('read_at')
                    ->whereColumn('sender_id', 'chat_conversations.user_id'),
            ])
            ->whereHas('messages')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'data' => $conversations->map(fn (ChatConversation $conversation): array => [
                'id' => $conversation->id,
                'user_id' => $conversation->user_id,
                'user_name' => $conversation->user->name,
                'last_message' => $conversation->latestMessage?->body,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread' => $conversation->unread_count,
            ])->values()->all(),
            'unread' => $this->chatService->unreadForAdmins(),
        ]);
    }

    public function adminMessages(ChatConversation $conversation): JsonResponse
    {
        return response()->json([
            'data' => $this->messagePayloads($conversation),
        ]);
    }

    public function adminSend(Request $request, ChatConversation $conversation): JsonResponse
    {
        $admin = $this->userFromRequest($request);
        $message = $this->chatService->sendAdminMessage($admin, $conversation, $this->validatedBody($request));

        return response()->json(['data' => $this->messagePayload($message)], 201);
    }

    public function adminRead(ChatConversation $conversation): JsonResponse
    {
        return response()->json([
            'data' => ['marked' => $this->chatService->markAdminMessagesRead($conversation)],
        ]);
    }

    public function adminPresence(Request $request): JsonResponse
    {
        $this->adminPresenceService->touch($this->userFromRequest($request));

        return response()->json(['online' => true]);
    }

    private function userFromRequest(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function validatedBody(Request $request): string
    {
        $body = trim($request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ])['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'The body field is required.',
            ]);
        }

        return $body;
    }

    private function messagePayloads(ChatConversation $conversation): array
    {
        return $conversation->messages()
            ->with('conversation', 'sender')
            ->oldest()
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $message): array => $this->messagePayload($message))
            ->all();
    }

    private function messagePayload(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->name,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'is_admin' => $message->sender->isAdmin(),
        ];
    }
}
