<?php

namespace App\Http\Controllers;

use App\Helper\ResponseHelper;
use App\Http\Requests\Opay\InitializePaymentRequest;
use App\Services\OpayService;
use Illuminate\Http\Request;
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
            return $opayService->handleCallback($request->all());
        } catch (Throwable $e) {
            report($e);
            return ResponseHelper::error('error', 'Callback processing failed: ' . $e->getMessage(), 500);
        }
    }
}
