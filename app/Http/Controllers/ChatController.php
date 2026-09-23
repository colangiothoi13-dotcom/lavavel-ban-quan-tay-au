<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\AdminPresenceService;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $user = $this->chatUserFromRequest($request);

        return view('chat.user', [
            'user' => $user,
            'isGuestChatUser' => $user instanceof User && $this->isGuestUser($user),
        ]);
    }

    public function startGuestChat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
        ]);
        $data['name'] = trim($data['name']);
        $data['phone'] = trim($data['phone']);

        if ($data['name'] === '') {
            throw ValidationException::withMessages([
                'name' => 'Vui lòng nhập họ và tên.',
            ]);
        }

        $user = $this->chatUserFromRequest($request);
        if (! $user instanceof User) {
            $user = $this->createGuestChatUser($request, $data['name']);
        } elseif ($this->isGuestUser($user)) {
            $user->update(['name' => $data['name']]);
        }

        $conversation = $this->chatService->conversationForUser($user);
        $conversation->update(['guest_phone' => $data['phone']]);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $conversation->guest_phone,
            ],
        ]);
    }

    public function userOverview(Request $request): JsonResponse
    {
        $user = $this->chatUserFromRequest($request);
        if ($user === null) {
            return response()->json(['data' => [], 'unread' => 0]);
        }

        $conversation = $this->chatService->conversationForUser($user);

        return response()->json([
            'data' => $this->messagePayloads($conversation, $user),
            'unread' => $this->chatService->unreadForUser($user),
        ]);
    }

    public function userMessages(Request $request): JsonResponse
    {
        $user = $this->chatUserFromRequest($request);
        if ($user === null) {
            return response()->json(['data' => []]);
        }

        $conversation = $this->chatService->conversationForUser($user);

        return response()->json([
            'data' => $this->messagePayloads($conversation, $user),
        ]);
    }

    public function userSend(Request $request): JsonResponse
    {
        $user = $this->chatUserFromRequest($request);
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'chat' => 'Vui lòng nhập tên và số điện thoại trước khi bắt đầu chat.',
            ]);
        }
        $message = $this->chatService->sendUserMessage($user, $this->validatedBody($request));

        return response()->json(['data' => $this->messagePayload($message, $user)], 201);
    }

    public function userRead(Request $request): JsonResponse
    {
        $user = $this->chatUserFromRequest($request);

        if ($user === null) {
            return response()->json(['data' => ['marked' => 0]]);
        }

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
                'user_phone' => $conversation->guest_phone,
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

    private function chatUserFromRequest(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $guestId = $request->session()->get('guest_chat_user_id');
        if ($guestId !== null) {
            $guest = User::query()
                ->whereKey($guestId)
                ->where('role', 'user')
                ->first();

            if ($guest instanceof User) {
                return $guest;
            }

            $request->session()->forget('guest_chat_user_id');
        }

        return null;
    }

    private function createGuestChatUser(Request $request, string $name): User
    {
        $token = (string) Str::uuid();
        $guest = User::query()->create([
            'name' => $name,
            'email' => 'guest-'.$token.'@guest.invalid',
            'password' => Str::random(64),
            'role' => 'user',
        ]);

        $request->session()->put('guest_chat_user_id', $guest->id);

        return $guest;
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

    private function messagePayloads(ChatConversation $conversation, ?User $currentUser = null): array
    {
        return $conversation->messages()
            ->with('conversation', 'sender')
            ->oldest()
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $message): array => $this->messagePayload($message, $currentUser))
            ->all();
    }

    private function messagePayload(ChatMessage $message, ?User $currentUser = null): array
    {
        $senderId = $message->sender_id;
        if ($currentUser && $this->isGuestUser($currentUser) && $message->sender_id === $currentUser->id) {
            $senderId = 'guest';
        }

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $senderId,
            'sender_name' => $message->sender->name,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'is_admin' => $message->sender->isAdmin(),
        ];
    }

    private function isGuestUser(User $user): bool
    {
        return str_ends_with($user->email, '@guest.invalid');
    }
}
