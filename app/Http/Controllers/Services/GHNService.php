<?php

namespace App\Http\Controllers\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GHNService
{
    public function getProvinces(): array
    {
        return collect($this->request('get', '/master-data/province'))
            ->filter(fn (array $province): bool => (int) ($province['Status'] ?? 1) === 1)
            ->groupBy(function (array $province): string {
                $name = Str::lower(Str::ascii((string) ($province['ProvinceName'] ?? '')));

                return preg_replace('/\s+\d+$/', '', $name);
            })
            ->map(fn ($duplicates) => $duplicates->sortBy('ProvinceID')->first())
            ->sortBy('ProvinceName')
            ->values()
            ->all();
    }

    public function getDistricts(int $provinceId): array
    {
        return collect($this->request('post', '/master-data/district', ['province_id' => $provinceId]))
            ->filter(fn (array $district): bool => (int) ($district['Status'] ?? 1) === 1)
            ->sortBy('DistrictName')
            ->values()
            ->all();
    }

    public function getWards(int $districtId): array
    {
        return collect($this->request('post', '/master-data/ward', ['district_id' => $districtId]))
            ->filter(fn (array $ward): bool => (int) ($ward['Status'] ?? 1) === 1)
            ->sortBy('WardName')
            ->values()
            ->all();
    }

    public function calculateFee(array $shipment): array
    {
        return $this->request('post', '/v2/shipping-order/fee', $shipment, true);
    }

    public function createOrder(array $order): array
    {
        return $this->request('post', '/v2/shipping-order/create', $order, true);
    }

    public function cancelOrder(string|array $orderCodes): array
    {
        $codes = is_array($orderCodes) ? $orderCodes : [$orderCodes];

        return $this->request('post', '/v2/switch-status/cancel', ['order_codes' => array_values($codes)], true);
    }

    private function client(bool $withShopId = false): PendingRequest
    {
        $token = (string) config('services.ghn.token');
        if ($token === '') {
            throw new RuntimeException('GHN_TOKEN chưa được cấu hình.');
        }

        $headers = ['Token' => $token, 'Content-Type' => 'application/json'];
        if ($withShopId) {
            $shopId = (string) config('services.ghn.shop_id');
            if ($shopId === '') {
                throw new RuntimeException('GHN_SHOP_ID chưa được cấu hình.');
            }
            $headers['ShopId'] = $shopId;
        }

        return Http::baseUrl(rtrim((string) config('services.ghn.base_url'), '/'))
            ->withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.ghn.timeout', 10))
            ->retry(2, 250, fn (Throwable $exception) => $exception instanceof ConnectionException)
            ->withOptions(['verify' => (bool) config('services.ghn.verify_ssl', true)]);
    }

    private function request(string $method, string $uri, array $payload = [], bool $withShopId = false): array
    {
        try {
            $response = $this->client($withShopId)->send(strtoupper($method), $uri, [
                $method === 'get' ? 'query' : 'json' => $payload,
            ]);
            $response->throw();
            $body = $response->json();

            if (! is_array($body) || (int) ($body['code'] ?? 0) !== 200) {
                throw new RuntimeException((string) ($body['message'] ?? 'GHN trả về dữ liệu không hợp lệ.'));
            }

            return $body['data'] ?? [];
        } catch (ConnectionException $exception) {
            report($exception);
            throw new RuntimeException('Không thể kết nối đến GHN. Vui lòng thử lại.', 0, $exception);
        } catch (RequestException $exception) {
            report($exception);
            $message = $exception->response?->json('message') ?: 'GHN từ chối yêu cầu.';
            throw new RuntimeException($message, $exception->response?->status() ?? 0, $exception);
        }
    }
}
