<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_homepage_but_protected_storefront_pages_require_login(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('shop.home'))->assertOk();

        $product = Product::query()->create([
            'name' => 'Sản phẩm khách được xem',
            'base_price' => 250000,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);

        $this->get(route('shop.products.show', $product))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('id="user-chat-toggle"', false)
            ->assertSee('data-chat-user-id="guest"', false);
        $this->get(route('shop.products.variant', [$product, $variant]))
            ->assertOk()
            ->assertSee($product->name);
        $this->get(route('cart.index'))->assertRedirect(route('login'));
        $this->get(route('checkout'))->assertRedirect(route('login'));
    }

    public function test_logout_returns_an_authenticated_user_to_the_homepage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('buyer.logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
