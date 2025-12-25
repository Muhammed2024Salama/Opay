<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class OpayService
{
    public function initializePayment(array $data): array
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

        if (! $response->successful()) {
            throw new Exception(
                $response->json()['message'] ?? 'Opay API error'
            );
        }

        return $response->json();
    }
}
