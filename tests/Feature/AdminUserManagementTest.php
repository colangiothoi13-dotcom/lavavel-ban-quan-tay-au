<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_chat_identities_are_not_shown_as_registered_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create([
            'name' => 'Khách chat',
            'email' => 'guest-test-token@guest.invalid',
            'role' => 'user',
        ]);
        $registeredUser = User::factory()->create([
            'name' => 'Người dùng thật',
            'email' => 'registered@example.com',
            'role' => 'user',
        ]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk()
            ->assertSee($registeredUser->email)
            ->assertDontSee($guest->email)
            ->assertDontSee($guest->name);
    }

    public function test_admin_can_only_update_user_role_and_not_other_profile_fields(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $response = $this->get('/admin/users');
        $response->assertOk();
        $response->assertSee('Quản lý người dùng');

        $this->get('/admin/users/create')->assertOk();

        $response = $this->post('/admin/users', [
            'name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'user',
            'phone' => '0987654321',
            'gender' => 'male',
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', ['email' => 'a@example.com', 'role' => 'user']);

        $user = User::query()->where('email', 'a@example.com')->firstOrFail();

        $this->get('/admin/users/' . $user->id . '/edit')->assertOk();

        $this->put('/admin/users/' . $user->id, [
            'role' => 'admin',
            'name' => 'Nguyễn Văn B',
            'email' => 'b@example.com',
            'phone' => '0909090909',
            'gender' => 'female',
        ])->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'phone' => '0987654321',
            'gender' => 'male',
            'role' => 'admin',
        ]);

        $response = $this->delete('/admin/users/' . $user->id);
        $response->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
