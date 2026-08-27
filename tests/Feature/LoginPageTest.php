<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_every_auth_page_uses_the_shared_static_visual(): void
    {
        $responses = [
            $this->get(route('login')),
            $this->get(route('register')),
            $this->get(route('password.request')),
            $this->get(route('password.otp')),
            $this->get(route('password.reset')),
            $this->withSession(['registration' => ['pending' => true]])->get(route('register.verify')),
        ];

        foreach ($responses as $response) {
            $response->assertOk();
            $response->assertSee('data-auth-visual', false);
            $response->assertSee('images/login/slide-1.jpg', false);
            $response->assertSee('slide-1.jpg?v=', false);
            $response->assertDontSee('data-login-slider', false);
            $response->assertDontSee('images/login/slide-2.png', false);
            $response->assertDontSee('setInterval', false);
        }

        $this->assertFileExists(public_path('images/login/slide-1.jpg'));
    }

    public function test_auth_pages_with_passwords_expose_password_visibility_controls(): void
    {
        foreach ([route('login'), route('register'), route('password.reset')] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('data-password-toggle', false);
            $response->assertSee('aria-label="Hiện mật khẩu"', false);
        }
    }

    public function test_only_the_login_page_displays_the_brand(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-auth-brand', false)
            ->assertSee('Minh Trí Tailor');

        $otherPages = [
            $this->get(route('register')),
            $this->get(route('password.request')),
            $this->get(route('password.otp')),
            $this->get(route('password.reset')),
            $this->withSession(['registration' => ['pending' => true]])->get(route('register.verify')),
        ];

        foreach ($otherPages as $response) {
            $response->assertOk();
            $response->assertDontSee('data-auth-brand', false);
            $response->assertDontSee('Minh Trí Tailor');
        }
    }
}
