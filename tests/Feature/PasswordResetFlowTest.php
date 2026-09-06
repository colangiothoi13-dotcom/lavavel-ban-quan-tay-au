<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_requires_otp_verification_session(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'email' => $user->email,
            'reset_token' => str_repeat('a', 40),
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422);

        $this->assertFalse(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_successful_otp_verification_creates_session_token_and_invalidates_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.otp'));
        $user->refresh();

        $this->post(route('password.verify'), [
            'email' => $user->email,
            'otp' => $user->reset_otp,
        ])->assertRedirect(route('password.reset'));

        $token = session('password_reset.token');
        $this->assertIsString($token);
        $this->assertSame(40, strlen($token));
        $this->assertNull($user->fresh()->reset_otp);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertNull(session('password_reset'));
    }
}