<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.user.{userId}', function (User $user, int $userId): bool {
    return $user->isAdmin() || $user->id === $userId;
});

Broadcast::channel('chat.admins', function (User $user): bool {
    return $user->isAdmin();
});
