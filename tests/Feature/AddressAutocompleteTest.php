<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_search_is_proxied_through_the_application(): void
    {
        Http::fake([
            'photon.komoot.io/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [106.7009, 10.7769]],
                    'properties' => ['name' => '10 Lê Thánh Tôn', 'city' => 'Hồ Chí Minh', 'country' => 'Việt Nam'],
                ]],
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('user.addresses.search', ['q' => '10 Lê Thánh Tôn']))
            ->assertOk()
            ->assertJsonPath('results.0.latitude', 10.7769)
            ->assertJsonPath('results.0.longitude', 106.7009);
    }

    public function test_address_search_uses_the_backup_provider_when_photon_fails(): void
    {
        Http::fake([
            'photon.komoot.io/*' => Http::response([], 503),
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => '10.7769',
                'lon' => '106.7009',
                'display_name' => '12 Đồng Khởi, Thành phố Hồ Chí Minh, Việt Nam',
                'name' => '12 Đồng Khởi',
                'address' => ['house_number' => '12', 'road' => 'Đồng Khởi'],
            ]]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('user.addresses.search', ['q' => '12 Đồng Khởi']))
            ->assertOk()
            ->assertJsonPath('results.0.street', '12 Đồng Khởi');
    }

    public function test_address_search_only_returns_results_in_the_selected_ward(): void
    {
        Http::fake([
            'photon.komoot.io/*' => Http::response([
                'features' => [
                    [
                        'geometry' => ['coordinates' => [105.763, 21.055]],
                        'properties' => ['name' => '41A', 'locality' => 'Phú Diễn', 'city' => 'Hà Nội'],
                    ],
                    [
                        'geometry' => ['coordinates' => [105.80, 21.04]],
                        'properties' => ['name' => '41A', 'locality' => 'Cầu Giấy', 'city' => 'Hà Nội'],
                    ],
                ],
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('user.addresses.search', [
                'q' => '41A, Phường Phú Diễn, Hà Nội',
                'ward' => 'Phường Phú Diễn',
                'province' => 'Thành phố Hà Nội',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.latitude', 21.055);
    }

    public function test_selected_map_coordinates_are_saved_with_the_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('user.addresses.store'), [
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0900000000',
            'city' => 'Phường Bến Nghé, Thành phố Hồ Chí Minh',
            'ghn_province_id' => 202,
            'ghn_district_id' => 1442,
            'ghn_ward_code' => '21211',
            'street_address' => '10 Lê Thánh Tôn',
            'map_url' => 'https://www.openstreetmap.org/?mlat=10.7769&mlon=106.7009',
            'latitude' => 10.7769,
            'longitude' => 106.7009,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'street_address' => '10 Lê Thánh Tôn',
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'ghn_province_id' => 202,
            'ghn_district_id' => 1442,
            'ghn_ward_code' => '21211',
        ]);
    }

    public function test_address_page_contains_the_ghn_district_selector(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.addresses.index'))
            ->assertOk()
            ->assertSee('Quận/Huyện')
            ->assertSee('id="district"', false)
            ->assertSee('ghn\/districts', false);
    }

    public function test_address_can_be_saved_after_selecting_ghn_ward_without_a_map_position(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('user.addresses.store'), [
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0900000000',
            'city' => 'Phường Bến Nghé, Thành phố Hồ Chí Minh',
            'ghn_province_id' => 202,
            'ghn_district_id' => 1442,
            'ghn_ward_code' => '21211',
            'street_address' => '10 Lê Thánh Tôn',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'street_address' => '10 Lê Thánh Tôn',
            'ghn_ward_code' => '21211',
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
