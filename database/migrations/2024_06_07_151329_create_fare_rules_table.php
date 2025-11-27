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
        Schema::create('fare_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aero_token_id')->constrained();
            $table->string('carrier');
            $table->string('fare_id');
            $table->json('rules')->nullable();
            $table->json('note')->nullable();
            $table->string('status')->default('updated');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fare_rules');
    }
};
