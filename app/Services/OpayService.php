<?php

namespace App\Services;

use App\Helper\ResponseHelper;
use App\Http\Resources\PaymentResource;
use App\Interfaces\OpayInterface;
use Illuminate\Http\JsonResponse;

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

    /**
     * Handle payment callback
     */
    public function handleCallback(array $payload): JsonResponse
    {
        $payment = $this->opayRepository->findPaymentByReference(
            $payload['reference'] ?? ''
        );

        if (!$payment) {
            return ResponseHelper::error('error', 'Payment not found', 404);
        }

        $payment = $this->opayRepository->updatePaymentByReference(
            $payment->reference,
            [
                'status'           => $payload['status'] === 'SUCCESS' ? 'success' : 'failed',
                'order_no'         => $payload['orderNo'] ?? $payment->order_no,
                'gateway_response' => $payload,
            ]
        );

        return ResponseHelper::success(
            'success',
            'Payment updated successfully',
            new PaymentResource($payment)
        );
    }
}
