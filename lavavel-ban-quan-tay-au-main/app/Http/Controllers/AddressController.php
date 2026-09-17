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
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:300'],
            'ward' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'specific_address' => ['nullable', 'string', 'max:300'],
        ]);

        $query = trim($validated['q']);
        $ward = trim($request->string('ward')->toString());
        $district = trim($request->string('district')->toString());
        $province = trim($request->string('province')->toString());
        $specificAddress = trim($request->string('specific_address')->toString());

        $cacheKey = 'address-search:v3:'.sha1(mb_strtolower($query));

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
            $results = [];
        }

        $filteredResults = $results;

        if ($ward !== '' || $district !== '') {
            $wardShort = $this->getShortName($ward);
            $districtShort = $this->getShortName($district);

            $filteredResults = collect($results)
                ->filter(function (array $result) use ($wardShort, $districtShort): bool {
                    $label = $this->normalizeSearchText($result['label'] ?? '');
                    
                    $matchWard = $wardShort !== '' && str_contains($label, $wardShort);
                    $matchDistrict = $districtShort !== '' && str_contains($label, $districtShort);

                    // We keep the result if it matches either ward or district
                    return $matchWard || $matchDistrict;
                })
                ->values()
                ->all();
        }

        // Always prepend a synthesized result for exactly what the user typed (e.g. house number)
        if ($specificAddress !== '' && $ward !== '') {
            $coords = $this->getCoordinatesForArea($ward, $district, $province);
            $synthesizedLabel = "$specificAddress, $ward, $district, $province";
            
            // Avoid duplicate if the first geocoded result is identical to our synthesized one
            if (empty($filteredResults) || $this->normalizeSearchText($filteredResults[0]['label']) !== $this->normalizeSearchText($synthesizedLabel)) {
                array_unshift($filteredResults, [
                    'label' => $synthesizedLabel,
                    'street' => $specificAddress,
                    'latitude' => $coords['latitude'] ?? 14.0583, // fallback to center Vietnam
                    'longitude' => $coords['longitude'] ?? 108.2772,
                ]);
            }
        }

        return response()->json(['results' => $filteredResults]);
    }

    private function getCoordinatesForArea(string $ward, string $district, string $province): ?array
    {
        $query = "$ward, $district, $province, Việt Nam";
        $cacheKey = 'area-coords:v1:'.sha1(mb_strtolower($query));
        
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($query) {
            try {
                $results = $this->searchPhoton($query);
                if (empty($results)) {
                    $results = $this->searchNominatim($query);
                }
                if (!empty($results)) {
                    return [
                        'latitude' => $results[0]['latitude'],
                        'longitude' => $results[0]['longitude'],
                    ];
                }
            } catch (Throwable $e) {}
            return null;
        });
    }

    private function getShortName(string $text): string
    {
        $normalized = $this->normalizeSearchText($text);
        $prefixes = ['phuong ', 'xa ', 'thi tran ', 'quan ', 'huyen ', 'thi xa ', 'thanh pho ', 'tp ', 'tinh '];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return trim(substr($normalized, strlen($prefix)));
            }
        }
        return $normalized;
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

    private function normalizeSearchText(string $text): string
    {
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', Str::lower(Str::ascii($text))), " \t\n\r");
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
