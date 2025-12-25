<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->string('reference')->unique();
            $table->string('order_no')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('EGP');

            $table->enum('status', array_map(fn($s) => $s->value, PaymentStatus::cases()))
                ->default(PaymentStatus::Pending->value);

            $table->string('gateway')->default('opay');

            $table->json('gateway_response')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
