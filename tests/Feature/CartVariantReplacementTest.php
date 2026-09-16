<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartVariantReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_replace_a_database_cart_item_when_session_cart_is_empty(): void
    {
        [$user, $oldVariant, $newVariant] = $this->cartVariants();
        $user->cartItems()->create([
            'product_variant_id' => $oldVariant->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('cart.replace-variant', $oldVariant), [
                'new_variant_id' => $newVariant->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('cart', [(string) $newVariant->id => 2]);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'product_variant_id' => $oldVariant->id,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_variant_id' => $newVariant->id,
            'quantity' => 2,
        ]);
    }

    public function test_replacing_with_a_variant_already_in_cart_merges_the_quantities(): void
    {
        [$user, $oldVariant, $newVariant] = $this->cartVariants();
        $user->cartItems()->createMany([
            ['product_variant_id' => $oldVariant->id, 'quantity' => 2],
            ['product_variant_id' => $newVariant->id, 'quantity' => 3],
        ]);

        $this->actingAs($user)
            ->post(route('cart.replace-variant', $oldVariant), [
                'new_variant_id' => $newVariant->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('cart', [(string) $newVariant->id => 5]);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_variant_id' => $newVariant->id,
            'quantity' => 5,
        ]);
    }

    /** @return array{User, ProductVariant, ProductVariant} */
    private function cartVariants(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Quần tây thử nghiệm',
            'base_price' => 250000,
        ]);
        $oldVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 100,
            'price' => 250000,
        ]);
        $newVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Ghi đậm',
            'size' => 'L',
            'stock' => 100,
            'price' => 250000,
        ]);

        return [$user, $oldVariant, $newVariant];
    }
}
