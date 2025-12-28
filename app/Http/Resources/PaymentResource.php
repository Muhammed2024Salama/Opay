<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'reference'        => $this->reference,
            'order_no'         => $this->order_no,
            'amount'           => $this->amount,
            'currency'         => $this->currency,
            'status'           => $this->status,
            'gateway'          => $this->gateway,
            'gateway_response' => $this->gateway_response,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
