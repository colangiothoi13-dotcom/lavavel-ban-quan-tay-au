<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MomoPaymentService
{
    private readonly array $config;

    public function __construct(
        array $config = []
    ) {
        $this->config = $config ?: config('services.momo', [
            'partner_code' => null,
            'access_key' => null,
            'secret_key' => null,
            'base_url' => 'https://test-payment.momo.vn',
            'create_endpoint' => '/v2/gateway/api/create',
            'return_route' => 'momo.result',
            'ipn_route' => 'momo.ipn',
            'sandbox' => true,
            'timeout' => 15,
        ]);
    }

    public function createPayment(Order $order, string $successRoute, string $failRoute): array
    {
        $this->assertConfiguration();

        $requestId = (string) Str::ulid();
        $momoOrderId = $this->buildOrderId($order->id, $requestId);
        $amount = (int) round((float) $order->total);
        $payload = [
            'partnerCode' => $this->config['partner_code'],
            'accessKey' => $this->config['access_key'],
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $momoOrderId,
            'orderInfo' => "Thanh toán đơn hàng #{$order->id}",
            'redirectUrl' => $successRoute,
            'ipnUrl' => $failRoute,
            'extraData' => base64_encode((string) $order->id),
            'requestType' => 'captureWallet',
            'lang' => 'vi',
        ];

        $payload['signature'] = $this->signCreate($payload);

        $response = $this->httpClient()
            ->post(rtrim($this->config['base_url'], '/').$this->config['create_endpoint'], $payload);

        if (! $response->ok()) {
            Log::warning('MoMo create payment request failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Không thể tạo yêu cầu thanh toán MoMo lúc này. Vui lòng thử lại.');
        }

        $data = $response->json();
        if (((int) ($data['resultCode'] ?? 1)) !== 0 || empty($data['payUrl'])) {
            $message = (string) ($data['message'] ?? 'Phản hồi không hợp lệ từ MoMo.');
            Log::warning('MoMo create payment returned failed', [
                'order_id' => $order->id,
                'result_code' => $data['resultCode'] ?? null,
                'message' => $message,
            ]);
            throw new RuntimeException($message);
        }

        $order->update([
            'payment_reference' => $momoOrderId,
            'momo_order_id' => $momoOrderId,
            'payment_expires_at' => now()->addMinutes((int) ($this->config['payment_timeout'] ?? 30)),
            'payment_method' => Order::PAYMENT_METHOD_MOMO,
        ]);

        return [
            'order_id' => $momoOrderId,
            'request_id' => $requestId,
            'pay_url' => $data['payUrl'],
            'full_response' => $data,
        ];
    }

    public function finalizeFromReturn(array $payload): array
    {
        $momoOrderId = (string) ($payload['orderId'] ?? '');
        if ($momoOrderId === '') {
            return ['status' => 'failed', 'message' => 'Thiếu mã giao dịch MoMo.'];
        }

        if (! $this->verifySignature($payload)) {
            return ['status' => 'failed', 'message' => 'Chữ ký phản hồi không hợp lệ.'];
        }

        $resultCode = (int) ($payload['resultCode'] ?? 1);
        $status = match ($resultCode) {
            0 => 'success',
            1006 => 'cancelled',
            default => 'failed',
        };

        $message = match ($resultCode) {
            0 => 'Thanh toán MoMo thành công.',
            1006 => 'Đơn đã bị hủy.',
            default => (string) ($payload['message'] ?? 'Thanh toán MoMo thất bại.'),
        };

        return ['status' => $status, 'message' => $message, 'momo_order_id' => $momoOrderId];
    }

    public function finalizeFromIpn(array $payload): array
    {
        if (! $this->verifySignature($payload)) {
            return ['status' => 'failed', 'result_code' => 1, 'message' => 'invalid_signature'];
        }

        $resultCode = (int) ($payload['resultCode'] ?? 1);
        $status = $resultCode === 0 ? 'success' : 'failed';

        return [
            'status' => $status,
            'result_code' => $resultCode,
            'momo_order_id' => (string) ($payload['orderId'] ?? ''),
            'message' => (string) ($payload['message'] ?? ''),
        ];
    }

    public function extractOrderIdFromReference(string $momoOrderId): ?int
    {
        if (preg_match('/^ORDER-(\d+)-/', $momoOrderId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['partner_code'])
            && ! empty($this->config['access_key'])
            && ! empty($this->config['secret_key']);
    }

    private function assertConfiguration(): void
    {
        if ($this->isConfigured()) {
            return;
        }

        throw new RuntimeException('Chưa cấu hình MoMo. Vui lòng điền thông tin trong .env');
    }

    private function buildOrderId(int $orderId, string $requestId): string
    {
        return "ORDER-{$orderId}-{$requestId}";
    }

    private function signCreate(array $payload): string
    {
        $string = implode('&', [
            "accessKey={$payload['accessKey']}",
            "amount={$payload['amount']}",
            "extraData={$payload['extraData']}",
            "ipnUrl={$payload['ipnUrl']}",
            "orderId={$payload['orderId']}",
            "orderInfo={$payload['orderInfo']}",
            "partnerCode={$payload['partnerCode']}",
            "redirectUrl={$payload['redirectUrl']}",
            "requestId={$payload['requestId']}",
            "requestType={$payload['requestType']}",
        ]);

        return hash_hmac('sha256', $string, (string) $this->config['secret_key']);
    }

    private function verifySignature(array $payload): bool
    {
        if (
            ! isset($payload['signature'])
            || ! isset($payload['orderId'])
            || ! isset($payload['resultCode'])
            || ! isset($payload['requestId'])
        ) {
            return false;
        }

        $expected = $this->signCallback((string) $payload['resultCode'], (string) $payload['orderId'], (string) ($payload['orderInfo'] ?? ''), (string) ($payload['requestId'] ?? ''));
        return hash_equals($expected, (string) $payload['signature']);
    }

    private function signCallback(string $resultCode, string $orderId, string $orderInfo, string $requestId): string
    {
        $parts = [
            "accessKey={$this->config['access_key']}",
            "orderInfo={$orderInfo}",
            "orderId={$orderId}",
            "requestId={$requestId}",
            "resultCode={$resultCode}",
        ];

        return hash_hmac('sha256', implode('&', $parts), (string) $this->config['secret_key']);
    }

    private function httpClient(): PendingRequest
    {
        return Http::asForm()->timeout((int) ($this->config['timeout'] ?? 15));
    }
}

