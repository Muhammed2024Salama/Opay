<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reference',
        'order_no',
        'amount',
        'currency',
        'status',
        'gateway',
        'gateway_response',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'status' => PaymentStatus::class,
    ];
}

