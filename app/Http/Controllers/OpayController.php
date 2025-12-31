<?php

namespace App\Http\Controllers;

use App\Helper\ResponseHelper;
use App\Http\Requests\Opay\InitializePaymentRequest;
use App\Services\OpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpayController extends Controller
{
    /**
     * Initialize payment and create record in database
     */
    public function pay(InitializePaymentRequest $request, OpayService $opayService)
    {
        try {
            return $opayService->initializePayment($request->validated());
        } catch (Throwable $e) {
            report($e);
            return ResponseHelper::error('error', 'Payment initialization failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Callback endpoint for Opay
     */
    public function callback(Request $request, OpayService $opayService)
    {
        try {
            if (!app()->environment('production')) {
                Log::debug('OPay callback received', [
                    'headers' => $request->headers->all(),
                    'body' => $request->all(),
                ]);
            }

            $rawBody = $request->getContent();

            return $opayService->handleCallback($request->all(), $rawBody);
        } catch (Throwable $e) {
            report($e);
            Log::error('OPay callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return ResponseHelper::error('error', 'Callback processing failed: ' . $e->getMessage(), 500);
        }
    }
}
