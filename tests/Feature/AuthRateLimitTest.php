<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_repeated_failed_attempts(): void
    {
        $user = User::factory()->create(['email' => 'login-limit@example.com', 'password' => 'correct-password']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_otp_is_throttled_after_repeated_failed_attempts(): void
    {
        Mail::fake();
        $registration = [
            'data' => [
                'name' => 'Limited Registration',
                'email' => 'registration-limit@example.com',
                'phone' => '0901234567',
                'password' => 'password123',
            ],
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $request = $this->withSession(['registration' => $registration]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $request->post(route('register.verify.submit'), ['otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $request->post(route('register.verify.submit'), ['otp' => '123456'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }
}
