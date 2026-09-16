<?php

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminPresenceService
{
    private const KEY_PREFIX = 'chat.admin.presence.';

    private const TTL_SECONDS = 75;

    public function touch(User $admin): void
    {
        $this->cache()->put(
            self::KEY_PREFIX.$admin->id,
            true,
            now()->addSeconds(self::TTL_SECONDS),
        );
    }

    public function isAnyAdminOnline(): bool
    {
        foreach (User::query()->where('role', 'admin')->pluck('id') as $adminId) {
            if ($this->cache()->has(self::KEY_PREFIX.$adminId)) {
                return true;
            }
        }

        return false;
    }

    private function cache()
    {
        return Cache::store(config('cache.default'));
    }
}
