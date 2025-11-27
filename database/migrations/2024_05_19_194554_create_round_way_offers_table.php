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
        Schema::create('round_way_offers', function (Blueprint $table) {
            $table->id();

            $table->string('uuid');
            $table->string('from');
            $table->string('to');
            $table->datetime('departure');
            $table->datetime('return');
            $table->integer('number_of_stops');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_way_offers');
    }
};
