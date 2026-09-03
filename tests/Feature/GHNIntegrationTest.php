<?php

namespace Tests\Feature;

use App\Http\Controllers\Services\GHNService;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GHNIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn', [
            'base_url' => 'https://dev-online-gateway.ghn.vn/shiip/public-api',
            'token' => 'test-token',
            'shop_id' => '216460',
            'verify_ssl' => false,
            'from_district_id' => 1450,
            'service_type_id' => 2,
            'default_weight' => 500,
            'default_length' => 20,
            'default_width' => 15,
            'default_height' => 10,
            'timeout' => 10,
        ]);
    }

    public function test_service_sends_ghn_headers_and_supports_master_data_create_and_cancel(): void
    {
        Http::fake([
            '*/master-data/province' => Http::response(['code' => 200, 'data' => [['ProvinceID' => 201]]]),
            '*/v2/shipping-order/create' => Http::response(['code' => 200, 'data' => ['order_code' => 'TEST123']]),
            '*/v2/switch-status/cancel' => Http::response(['code' => 200, 'data' => [['order_code' => 'TEST123', 'result' => true]]]),
        ]);
        $service = app(GHNService::class);

        $this->assertCount(1, $service->getProvinces());
        $this->assertSame('TEST123', $service->createOrder(['to_name' => 'Khách'])['order_code']);
        $this->assertTrue($service->cancelOrder('TEST123')[0]['result']);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Token', 'test-token'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/shipping-order/create')
            && $request->hasHeader('ShopId', '216460'));
    }

    public function test_fee_endpoint_uses_a_fixed_light_clothing_package(): void
    {
        Http::fake([
            '*/v2/shipping-order/fee' => Http::response(['code' => 200, 'data' => ['total' => 32000]]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('ghn.fee'), [
            'to_district_id' => 1442,
            'to_ward_code' => '21211',
            'insurance_value' => 500000,
        ])->assertOk()->assertJsonPath('data.total', 32000);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/shipping-order/fee')
            && $request['weight'] === 500
            && $request['from_district_id'] === 1450);
    }

    public function test_checkout_recalculates_shipping_fee_and_stores_the_final_total(): void
    {
        Http::fake([
            '*/v2/shipping-order/fee' => Http::response(['code' => 200, 'data' => ['total' => 30000]]),
        ]);
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây', 'base_price' => 250000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'M',
            'stock' => 5,
            'price' => 250000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($user)
            ->post(route('checkout.place'), [
                'selected_items' => [$variant->id],
                'name' => 'Nguyễn Văn A',
                'phone' => '0900000000',
                'address' => '41A, Phường Phú Diễn, Hà Nội',
                'payment_method' => 'cash',
                'ghn_district_id' => 1485,
                'ghn_ward_code' => '1A0213',
            ])->assertSessionHasNoErrors()->assertRedirect(route('shop.home'));

        $order = Order::query()->firstOrFail();
        $this->assertSame(30000.0, (float) $order->shipping_fee);
        $this->assertSame(530000.0, (float) $order->total);
        $this->assertSame(1485, $order->ghn_district_id);
        $this->assertSame('1A0213', $order->ghn_ward_code);
    }

    public function test_checkout_shows_ghn_shipping_and_does_not_ask_for_weight(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Quần tây nhẹ', 'base_price' => 300000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Xám',
            'size' => 'L',
            'stock' => 3,
            'price' => 300000,
        ]);
        $user->cartItems()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->get(route('checkout', ['selected_items' => [$variant->id]]))
            ->assertOk()
            ->assertSee('Phí vận chuyển GHN')
            ->assertSee('Tổng tiền phải trả')
            ->assertSee('Quận/Huyện')
            ->assertDontSee('Nhập cân nặng');
    }
}
