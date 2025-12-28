<?php

namespace App\Repositories;

use App\Interfaces\OpayInterface;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Http;

class OpayRepository implements OpayInterface
{
    /**
     * Create a new payment record
     */
    public function createPayment(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Update payment by reference
     */
    public function updatePaymentByReference(string $reference, array $data): Payment
    {
        $payment = $this->findPaymentByReference($reference);

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        $payment->update($data);

        return $payment->fresh();
    }

    /**
     * Find payment by reference
     */
    public function findPaymentByReference(string $reference): ?Payment
    {
        return Payment::where('reference', $reference)->first();
    }

    /**
     * Initialize payment with Opay gateway
     */
    public function initializePaymentWithGateway(array $data): array
    {
        $payload = [
            "amount" => [
                "currency" => "EGP",
                "total"    => $data['amount'],
            ],
            "country" => "EG",
            "merchantReference" => $data['reference'],
            "callbackUrl" => $data['callback_url'],
            "returnUrl"   => $data['return_url'],
            "product" => [
                "name"        => "Order Payment",
                "description" => "Order #" . $data['reference'],
            ],
            "userInfo" => [
                "userId"    => (string) $data['user_id'],
                "userName"  => $data['user_name'],
                "userEmail" => $data['user_email'],
            ],
        ];

        $response = Http::timeout(15)
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('opay.secret_key'),
                'MerchantId'    => config('opay.merchant_code'),
                'Content-Type'  => 'application/json',
            ])
            ->post(
                config('opay.base_url') . '/api/v1/international/cashier/create',
                $payload
            );

        if (!$response->successful()) {
            throw new Exception(
                $response->json()['message'] ?? 'Opay API error'
            );
        }

        return $response->json();
    }
}

