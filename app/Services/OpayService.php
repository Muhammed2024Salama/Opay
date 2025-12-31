<?php

namespace App\Services;

use App\Helper\ResponseHelper;
use App\Http\Resources\PaymentResource;
use App\Interfaces\OpayInterface;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OpayService
{
    public function __construct(
        private OpayInterface $opayRepository
    ) {
    }

    /**
     * Initialize payment process
     */
    public function initializePayment(array $requestData): JsonResponse
    {
        $reference = 'ORDER_' . time() . '_' . rand(1000, 9999);
        $order_no  = 'ORD-' . time() . rand(1000, 9999);

        $payment = $this->opayRepository->createPayment([
            'reference' => $reference,
            'order_no'  => $order_no,
            'amount'    => $requestData['amount'],
            'status'    => 'pending',
            'currency'  => 'EGP',
            'gateway'   => 'opay',
        ]);

        $response = $this->opayRepository->initializePaymentWithGateway([
            'amount'       => $payment->amount,
            'reference'    => $payment->reference,
            'user_id'      => $requestData['user_id'],
            'user_name'    => $requestData['user_name'],
            'user_email'   => $requestData['user_email'],
            'callback_url' => route('opay.callback'),
            'return_url'   => config('opay.return_url'),
        ]);

        $updateData = ['gateway_response' => $response];

        if (!empty($response['data']['orderNo'])) {
            $updateData['order_no'] = $response['data']['orderNo'];
        }

        $payment = $this->opayRepository->updatePaymentByReference(
            $payment->reference,
            $updateData
        );

        return ResponseHelper::success(
            'success',
            'Payment initialized successfully',
            new PaymentResource($payment)
        );
    }

    private function verifyCallbackSignature(string $rawBody, string $receivedSignature): bool
    {
        if (app()->environment('local') && config('opay.skip_signature_verification', false)) {
            Log::info('OPay callback signature verification skipped (local environment)');
            return true;
        }

        $secretKey = config('opay.secret_key');

        if (!$receivedSignature || !$secretKey) {
            Log::warning('OPay callback signature verification: Missing signature or secret key', [
                'has_signature' => !empty($receivedSignature),
                'has_secret_key' => !empty($secretKey)
            ]);
            return false;
        }

        $calculatedSignature = hash_hmac('sha512', $rawBody, $secretKey);

        $isValid = hash_equals($calculatedSignature, $receivedSignature);

        if (!$isValid) {
            Log::warning('OPay callback signature verification failed', [
                'raw_body_length' => strlen($rawBody),
            ]);
            if (!app()->environment('production')) {
                Log::debug('OPay signature details', [
                    'received_signature'   => $receivedSignature,
                    'calculated_signature' => $calculatedSignature,
                    'raw_body_preview'     => substr($rawBody, 0, 100) . '...'
                ]);
            }
        } else {
            if (!app()->environment('production')) {
                Log::debug('OPay callback signature verified successfully');
            }
        }

        return $isValid;
    }

    public function handleCallback(array $requestData, string $rawBody = ''): JsonResponse
    {
        if (!app()->environment('production')) {
            Log::debug('OPay callback received', [
                'request_keys' => array_keys($requestData),
                'has_payload'  => isset($requestData['payload']),
            ]);
        }

        $payload = $requestData['payload'] ?? null;

        $receivedSignature = request()->header('Signature')
            ?? request()->header('X-OPAY-SIGNATURE')
            ?? request()->header('X-Opay-Signature')
            ?? null;

        if (!$payload) {
            Log::warning('OPay callback missing payload');
            return ResponseHelper::error('error', 'Invalid callback structure: payload is missing', 400);
        }

        if (!$receivedSignature) {
            Log::warning('OPay callback missing signature in header');
            return ResponseHelper::error('error', 'Invalid callback structure: signature header is missing', 400);
        }

        if (!is_array($payload)) {
            Log::warning('OPay callback payload is not an array', [
                'payload_type' => gettype($payload)
            ]);

            return ResponseHelper::error('error', 'Invalid callback structure: payload must be an object', 400);
        }

        if (empty($rawBody)) {
            $rawBody = json_encode($requestData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            Log::warning('OPay callback: Using reconstructed raw body (may not match OPay signature)');
        }

        if (!$this->verifyCallbackSignature($rawBody, $receivedSignature)) {
            return ResponseHelper::error('error', 'Invalid callback signature', 401);
        }

        $reference = $payload['reference'] ?? null;
        $orderNo   = $payload['transactionId'] ?? $payload['orderNo'] ?? null;

        if (!$reference) {
            return ResponseHelper::error('error', 'Reference not found in callback', 400);
        }

        if (!$orderNo) {
            return ResponseHelper::error('error', 'Order number not found in callback', 400);
        }

        $payment = $this->opayRepository->findPaymentByReference($reference);

        if (!$payment) {
            return ResponseHelper::error('error', 'Payment not found', 404);
        }

        if ($payment->status === 'success') {
            return ResponseHelper::success(
                'success',
                'Payment already processed',
                new PaymentResource($payment)
            );
        }

        try {
            $opayResponse = $this->opayRepository->queryPaymentStatus($orderNo, $reference);

            $opayData = $opayResponse['data'] ?? [];

            $validationErrors = $this->validatePaymentData($payment, $opayData, $payload);

            if (!empty($validationErrors)) {
                return ResponseHelper::error(
                    'error',
                    'Payment data validation failed: ' . implode(', ', $validationErrors),
                    400
                );
            }

            $opayStatus = strtoupper($opayData['status'] ?? '');
            $status = match ($opayStatus) {
                'SUCCESS'       => 'success',
                'PENDING'       => 'pending',
                'FAIL', 'CLOSE' => 'failed',
                default         => 'failed',
            };

            $payment = $this->opayRepository->updatePaymentByReference(
                $payment->reference,
                [
                    'status'           => $status,
                    'order_no'         => $opayData['orderNo'] ?? $orderNo,
                    'gateway_response' => [
                        'callback'  => $requestData,
                        'query_api' => $opayResponse,
                    ],
                ]
            );

            return ResponseHelper::success(
                'success',
                'Payment updated successfully',
                new PaymentResource($payment)
            );

        } catch (\Exception $e) {
            return ResponseHelper::error(
                'error',
                'Failed to verify payment status: ' . $e->getMessage(),
                500
            );
        }
    }

    private function validatePaymentData(Payment $payment, array $opayData, array $callbackPayload): array
    {
        $errors = [];

        $opayReference = $opayData['reference'] ?? $opayData['merchantReference'] ?? null;
        if ($opayReference !== $payment->reference) {
            $errors[] = "Reference mismatch: expected {$payment->reference}, got {$opayReference}";
        }

        $opayAmount = $opayData['amount']['total'] ?? $opayData['amount'] ?? null;
        if ($opayAmount === null) {
            $errors[] = "Amount is empty";
        } elseif ((int)$opayAmount !== (int)$payment->amount) {
            $errors[] = "Amount mismatch: expected {$payment->amount}, got {$opayAmount}";
        }

        $opayCurrency = $opayData['amount']['currency'] ?? $opayData['currency'] ?? null;
        if ($opayCurrency && strtoupper($opayCurrency) !== strtoupper($payment->currency)) {
            $errors[] = "Currency mismatch: expected {$payment->currency}, got {$opayCurrency}";
        }

        return $errors;
    }
}
