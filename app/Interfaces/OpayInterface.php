<?php

namespace App\Interfaces;

use App\Models\Payment;

interface OpayInterface
{
    /**
     * Create a new payment record
     */
    public function createPayment(array $data): Payment;

    /**
     * Update payment by reference
     */
    public function updatePaymentByReference(string $reference, array $data): Payment;

    /**
     * Find payment by reference
     */
    public function findPaymentByReference(string $reference): ?Payment;

    /**
     * Initialize payment with Opay gateway
     */
    public function initializePaymentWithGateway(array $data): array;

    /**
     * Query payment status from OPay server
     * This is the source of truth for payment status (Server-to-Server verification)
     */
    public function queryPaymentStatus(string $orderNo, string $reference): array;
}

