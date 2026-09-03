<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AddressController
{
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->validate([
            'q' => ['required', 'string', 'min:2', 'max:300'],
            'ward' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
        ])['q']);
        $ward = trim($request->string('ward')->toString());

        $cacheKey = 'address-search:v2:'.sha1(mb_strtolower($query));

        try {
            $results = Cache::remember($cacheKey, now()->addDay(), function () use ($query): array {
                try {
                    $results = $this->searchPhoton($query);

                    return $results !== [] ? $results : $this->searchNominatim($query);
                } catch (Throwable) {
                    return $this->searchNominatim($query);
                }
            });
        } catch (Throwable) {
            return response()->json([
                'message' => 'Không thể kết nối dịch vụ bản đồ. Vui lòng thử lại sau.',
                'results' => [],
            ], 503);
        }

        if ($ward !== '') {
            $wardName = preg_replace('/^(phuong|xa|thi tran)\s+/u', '', Str::lower(Str::ascii($ward)));
            $filterByWard = fn (array $items): array => collect($items)
                    ->filter(fn (array $result): bool => str_contains(Str::lower(Str::ascii($result['label'])), $wardName))
                    ->values()
                    ->all();
            $results = $filterByWard($results);

            if ($results === []) {
                try {
                    $backupResults = Cache::remember($cacheKey.':nominatim', now()->addDay(), fn (): array => $this->searchNominatim($query));
                    $results = $filterByWard($backupResults);
                } catch (Throwable) {
                    // Giữ danh sách rỗng nếu cả nguồn dự phòng cũng không truy cập được.
                }
            }
        }

        return response()->json(['results' => $results]);
    }

    public function index(Request $request): View
    {
        return view('addresses.index', [
            'addresses' => $request->user()->addresses()->latest('is_default')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:255'],
            'ghn_province_id' => ['required', 'integer', 'min:1'],
            'ghn_district_id' => ['required', 'integer', 'min:1'],
            'ghn_ward_code' => ['required', 'string', 'max:20'],
            'street_address' => ['required', 'string', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $address = $request->user()->addresses()->create($data);
        if ($request->boolean('is_default') || $request->user()->addresses()->count() === 1) {
            $this->setDefault($address);
        }

        return back()->with('status', 'Đã lưu địa chỉ nhận hàng.');
    }

    public function makeDefault(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $this->setDefault($address);

        return back()->with('status', 'Đã chọn địa chỉ mặc định.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', 'Đã xóa địa chỉ.');
    }

    private function setDefault(Address $address): void
    {
        $address->user->addresses()->where('id', '<>', $address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    private function searchPhoton(string $query): array
    {
        $features = Http::acceptJson()
            ->timeout(6)
            ->retry(1, 200)
            ->get('https://photon.komoot.io/api/', [
                'q' => $query,
                'limit' => 7,
                'lang' => 'vi',
            ])
            ->throw()
            ->json('features', []);

        return collect($features)->map(function (array $feature): ?array {
            $properties = $feature['properties'] ?? [];
            $coordinates = $feature['geometry']['coordinates'] ?? [];
            if (! isset($coordinates[0], $coordinates[1])) {
                return null;
            }

            $specific = ! empty($properties['housenumber']) && ! empty($properties['street'])
                ? $properties['housenumber'].' '.$properties['street']
                : ($properties['name'] ?? $properties['street'] ?? '');
            $label = collect([
                $specific,
                $properties['district'] ?? null,
                $properties['locality'] ?? null,
                $properties['county'] ?? null,
                $properties['city'] ?? null,
                $properties['state'] ?? null,
                $properties['country'] ?? null,
            ])->filter()->unique()->implode(', ');

            return [
                'label' => $label,
                'street' => $specific ?: $label,
                'latitude' => (float) $coordinates[1],
                'longitude' => (float) $coordinates[0],
            ];
        })->filter()->values()->all();
    }

    private function searchNominatim(string $query): array
    {
        $places = Http::acceptJson()
            ->withUserAgent('LarVidu1/1.0 address search')
            ->timeout(6)
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 7,
                'addressdetails' => 1,
                'accept-language' => 'vi',
                'countrycodes' => 'vn',
            ])
            ->throw()
            ->json();

        return collect($places)->map(function (array $place): array {
            $address = $place['address'] ?? [];
            $specific = collect([
                $address['house_number'] ?? null,
                $address['road'] ?? $address['pedestrian'] ?? $address['neighbourhood'] ?? null,
            ])->filter()->implode(' ');

            return [
                'label' => $place['display_name'] ?? $specific,
                'street' => $specific ?: ($place['name'] ?? $place['display_name'] ?? ''),
                'latitude' => (float) $place['lat'],
                'longitude' => (float) $place['lon'],
            ];
        })->values()->all();
    }
}
