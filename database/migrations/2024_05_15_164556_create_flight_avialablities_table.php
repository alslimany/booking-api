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
        Schema::create('flight_availablities', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->foreignId('flight_schedule_id')->constrained();
            $table->string('name');
            $table->string('display_name');
            $table->string('cabin');
            $table->string('class');
            $table->boolean('is_international')->default(false);
            $table->integer('seats')->default(0);
            $table->decimal('price')->default(0);
            $table->decimal('tax')->default(0);
            $table->decimal('miles')->default(0);
            $table->boolean('fare_available')->default(false);
            $table->integer('fare_id')->default(0);
            $table->json('rules')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_availablities');
    }
};
