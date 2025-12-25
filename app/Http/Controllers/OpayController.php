<?php

namespace App\Http\Controllers;

use App\Http\Requests\Opay\InitializePaymentRequest;
use App\Services\OpayService;
use App\Models\Payment;
use App\Helper\ResponseHelper;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Throwable;

class OpayController extends Controller
{
    /**
     * Initialize payment and create record in database
     */
    public function pay(
        InitializePaymentRequest $request,
        OpayService $opayService
    ) {
        try {
            $reference = 'ORDER_' . time() . '_' . rand(1000, 9999);

            $payment = Payment::create([
                'reference' => $reference,
                'amount'    => $request->amount,
                'status'    => 'pending',
                'currency'  => 'EGP',
                'gateway'   => 'opay',
            ]);

            $response = $opayService->initializePayment([
                'amount'       => $payment->amount,
                'reference'    => $payment->reference,
                'user_id'      => $request->user_id,
                'user_name'    => $request->user_name,
                'user_email'   => $request->user_email,
                'callback_url' => route('opay.callback'),
                'return_url'   => config('opay.return_url'),
            ]);

            $payment->update([
                'order_no'         => $response['data']['orderNo'] ?? null,
                'gateway_response' => $response,
            ]);

            return ResponseHelper::success(
                'success',
                'Payment initialized successfully',
                new PaymentResource($payment)
            );
        } catch (Throwable $e) {
            report($e);

            return ResponseHelper::error(
                'error',
                'Payment initialization failed: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Callback endpoint for Opay
     */
    public function callback(Request $request)
    {
        try {
            $payload = $request->all();

            $payment = Payment::where('reference', $payload['reference'] ?? null)->first();

            if (! $payment) {
                return ResponseHelper::error('error', 'Payment not found', 404);
            }

            $payment->update([
                'status'           => $payload['status'] === 'SUCCESS' ? 'success' : 'failed',
                'order_no'         => $payload['orderNo'] ?? $payment->order_no,
                'gateway_response' => $payload,
            ]);

            return ResponseHelper::success(
                'success',
                'Payment updated successfully',
                new PaymentResource($payment)
            );

        } catch (Throwable $e) {
            report($e);

            return ResponseHelper::error(
                'error',
                'Callback processing failed: ' . $e->getMessage(),
                500
            );
        }
    }
}
