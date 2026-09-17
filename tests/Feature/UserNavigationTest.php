<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_layout_uses_a_collapsible_navigation_menu(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('user.profile.show'))
            ->assertOk()
            ->assertSee('data-user-menu-toggle', false)
            ->assertSee('data-user-menu', false)
            ->assertSee('data-user-menu-backdrop', false)
            ->assertSee('aria-expanded="false"', false);
    }

    public function test_admin_layout_does_not_include_the_user_menu_toggle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="admin-sidebar"', false)
            ->assertDontSee('class="user-menu-toggle" data-user-menu-toggle', false);
    }

    public function test_user_header_links_to_the_cart_before_the_account_greeting(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'name' => 'Nguyen Van An',
        ]);

        $this->actingAs($user)
            ->get(route('user.profile.show'))
            ->assertOk()
            ->assertSee('data-user-cart-link', false)
            ->assertSee('aria-label="Giỏ hàng"', false)
            ->assertSeeInOrder([
                'href="'.route('cart.index').'"',
                'Xin chào! Nguyen Van An',
            ], false);
    }

    public function test_user_layout_keeps_two_pixel_gaps_between_navigation_bars_and_content(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $content = $this->actingAs($user)
            ->get(route('user.profile.show'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            preg_match('/\\.user-shell\\s*\\{[^}]*gap:\\s*2px;/s', $content),
            'The user shell must keep a 2px gap between the sidebar and main area.'
        );
        $this->assertSame(
            1,
            preg_match('/\\.user-main\\s*\\{[^}]*gap:\\s*2px;/s', $content),
            'The user main area must keep a 2px gap between the header and content.'
        );
    }

    public function test_user_layout_uses_a_visible_gutter_and_rounded_content_frame(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $content = $this->actingAs($user)
            ->get(route('user.profile.show'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            preg_match('/\.user-shell\s*\{[^}]*background:\s*#e2e8f0;/s', $content),
            'The user shell must expose a light gutter between the navigation bars and content.'
        );
        $this->assertSame(
            1,
            preg_match('/\.user-card\s*\{[^}]*border-radius:\s*20px;/s', $content),
            'The user content frame must keep the rounded shape shown in the reference layout.'
        );
    }
}
