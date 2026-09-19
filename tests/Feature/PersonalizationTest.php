<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_wishlist_and_view_recommendations(): void
    {
        $category = Category::create(['name' => 'Áo vest', 'description' => 'Áo vest']);

        $boughtProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Áo vest đen',
            'description' => 'Áo vest đen premium',
            'gender' => 'male',
            'base_price' => 1200000,
            'image' => null,
        ]);

        $recommendedProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Áo vest xám',
            'description' => 'Áo vest xám tối giản',
            'gender' => 'male',
            'base_price' => 1300000,
            'image' => null,
        ]);

        $otherProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Quần Tây nữ',
            'description' => 'Quần nữ khác',
            'gender' => 'female',
            'base_price' => 900000,
            'image' => null,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('shop.wishlist.toggle', $boughtProduct))->assertRedirect();
        $this->assertDatabaseHas('user_wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $boughtProduct->id,
        ]);

        $order = $user->orders()->create([
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0999999999',
            'address' => 'Hà Nội',
            'payment_method' => 'cod',
            'status' => 'completed',
            'total' => 1200000,
        ]);

        $order->items()->create([
            'product_variant_id' => null,
            'product_name' => $boughtProduct->name,
            'variant_name' => 'Mã',
            'quantity' => 1,
            'price' => 1200000,
        ]);

        $response = $this->get(route('shop.recommendations'));
        $response->assertOk();
        $response->assertSee($recommendedProduct->name);
        $response->assertDontSee($otherProduct->name);
    }
}
