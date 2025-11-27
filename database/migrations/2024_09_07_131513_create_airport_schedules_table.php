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
        Schema::create('airport_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('ref_id')->nullable();
            $table->string('type'); // arrivals - departure - ground
            $table->string('number')->nullable();
            $table->string('airline_iata')->nullable();
            $table->string('status');
            $table->timestamp('status_at')->nullable();
            $table->string('aircraft');
            $table->string('origin');
            $table->string('destination');
            $table->timestamp('scheduled_departure_at');
            $table->timestamp('scheduled_arrival_at');
            // $table->timestamp('estimated_at');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airport_schedules');
    }
};
