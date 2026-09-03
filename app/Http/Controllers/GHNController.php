<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Services\GHNService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GHNController extends Controller
{
    public function __construct(private readonly GHNService $ghn) {}

    public function provinces(): JsonResponse
    {
        return $this->respond(fn () => $this->ghn->getProvinces());
    }

    public function districts(Request $request): JsonResponse
    {
        $id = $request->validate(['province_id' => ['required', 'integer', 'min:1']])['province_id'];

        return $this->respond(fn () => $this->ghn->getDistricts((int) $id));
    }

    public function wards(Request $request): JsonResponse
    {
        $id = $request->validate(['district_id' => ['required', 'integer', 'min:1']])['district_id'];

        return $this->respond(fn () => $this->ghn->getWards((int) $id));
    }

    public function fee(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to_district_id' => ['required', 'integer', 'min:1'],
            'to_ward_code' => ['required', 'string', 'max:20'],
            'insurance_value' => ['required', 'integer', 'min:0', 'max:5000000'],
        ]);

        return $this->respond(fn () => $this->ghn->calculateFee($this->feePayload($data)));
    }

    private function feePayload(array $data): array
    {
        return $data + [
            'from_district_id' => (int) config('services.ghn.from_district_id'),
            'service_type_id' => (int) config('services.ghn.service_type_id', 2),
            'weight' => (int) config('services.ghn.default_weight', 500),
            'length' => (int) config('services.ghn.default_length', 20),
            'width' => (int) config('services.ghn.default_width', 15),
            'height' => (int) config('services.ghn.default_height', 10),
        ];
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }
}
