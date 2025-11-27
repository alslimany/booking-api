<?php

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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('order_id')->constrained();
            $table->string('type'); // ticket, car, insurance
            $table->string('provider')->nullable();
            $table->string('status')->nullable()->default('issued');
            $table->string('reference'); // reference number PNR, Insurance Paper Number, etc
            $table->decimal('price');
            $table->decimal('taxes');
            $table->decimal('total');
            $table->decimal('paid')->default(0);
            $table->decimal('remaning')->default(0);
            $table->decimal('agent_commission')->default(0);
            $table->decimal('net_commission')->default(0);
            $table->string('currency_code');
            $table->decimal('exchange_rate')->default(1);
            $table->json('item');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
