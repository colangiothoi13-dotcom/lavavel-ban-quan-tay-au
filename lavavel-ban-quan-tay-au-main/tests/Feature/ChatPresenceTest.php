<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatPresenceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_a_recent_heartbeat_from_any_admin_keeps_users_online(): void
    {
        $firstAdmin = User::factory()->create(['role' => 'admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($firstAdmin)->postJson(route('chat.admin.presence'));
        $this->travel(60)->seconds();
        $this->actingAs($secondAdmin)->postJson(route('chat.admin.presence'));
        $this->travel(16)->seconds();

        $this->assertFalse(Cache::has('chat.admin.presence.'.$firstAdmin->id));
        $this->assertTrue(Cache::has('chat.admin.presence.'.$secondAdmin->id));

        $this->actingAs($user)->getJson(route('chat.user.admin-status'))
            ->assertOk()->assertJson(['online' => true]);
    }

    public function test_presence_cache_key_and_ttl_boundary_are_observable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)->postJson(route('chat.admin.presence'))
            ->assertOk()->assertJson(['online' => true]);

        $this->assertTrue(Cache::has('chat.admin.presence.'.$admin->id));

        $this->travel(74)->seconds();
        $this->actingAs($user)->getJson(route('chat.user.admin-status'))
            ->assertOk()->assertJson(['online' => true]);

        $this->travel(2)->seconds();
        $this->actingAs($user)->getJson(route('chat.user.admin-status'))
            ->assertOk()->assertJson(['online' => false]);
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

    public function test_regular_user_cannot_send_presence_heartbeat(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson(route('chat.admin.presence'))->assertForbidden();
    }
}
