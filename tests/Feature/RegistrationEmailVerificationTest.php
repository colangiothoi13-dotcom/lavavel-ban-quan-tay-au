<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pending_registration_can_request_a_new_verification_code(): void
    {
        Mail::fake();

        $registration = [
            'data' => $this->registrationData(),
            'otp' => '111111',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $response = $this->withSession(['registration' => $registration])
            ->post(route('register.verify.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertNotSame('111111', session('registration.otp'));
        $this->assertGreaterThan(now()->timestamp, session('registration.expires_at'));
    }

    public function test_a_valid_code_creates_an_email_verified_user(): void
    {
        $registration = [
            'data' => $this->registrationData(),
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $this->withSession(['registration' => $registration])
            ->post(route('register.verify.submit'), ['otp' => '123456'])
            ->assertRedirect(route('home'));

        $user = User::where('email', 'verification@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_resent_code_replaces_an_expired_code_and_can_be_used(): void
    {
        Mail::fake();

        $registration = [
            'data' => $this->registrationData(),
            'otp' => '111111',
            'expires_at' => now()->subMinute()->timestamp,
        ];

        $this->withSession(['registration' => $registration])
            ->post(route('register.verify.resend'))
            ->assertSessionHasNoErrors();

        $newOtp = session('registration.otp');
        $this->assertNotSame('111111', $newOtp);
        $this->assertGreaterThanOrEqual(
            now()->addMinutes(14)->timestamp,
            session('registration.expires_at')
        );

        $this->post(route('register.verify.submit'), ['otp' => $newOtp])
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['email' => 'verification@example.com']);
    }

    private function registrationData(): array
    {
        return [
            'name' => 'Verification User',
            'email' => 'verification@example.com',
            'phone' => '0901234567',
            'password' => 'password123',
        ];
    }
}
