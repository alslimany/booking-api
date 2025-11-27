<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('round_way_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_way_segment_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('flight_availablity_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('flight_schedule_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('passenger_type');
            $table->string('from');
            $table->string('to');
            $table->datetime('departure');
            $table->datetime('arrival');
            $table->string('cabin');
            $table->string('class');
            $table->string('fare_basis');
            $table->decimal('fare_price');
            $table->decimal('tax');
            $table->decimal('price');
            $table->string('currency');
            $table->string('hold_pices');
            $table->string('hold_weight');
            $table->string('hand_weight');
            $table->string('command');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_way_pricings');
    }
};
