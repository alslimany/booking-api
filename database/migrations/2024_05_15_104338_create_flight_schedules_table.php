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
        Schema::create('flight_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->foreignId('aero_token_id')->constrained();
            $table->string('iata');
            $table->string('origin');
            $table->string('destination');
            $table->string('flight_number');
            $table->datetime('departure');
            $table->datetime('arrival');
            $table->integer('duration')->default(0);
            $table->boolean('has_offers')->default(true);
            $table->timestamp('canceled_at')->nullable()->default(null);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_schedules');
    }
};
