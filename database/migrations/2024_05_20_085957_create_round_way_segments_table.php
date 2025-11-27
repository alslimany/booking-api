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
        Schema::create('round_way_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_way_offer_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('flight_schedule_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('type')->comment('segment type [goint,return]');
            $table->string('from');
            $table->string('to');
            $table->datetime('departure');
            $table->datetime('arrival');
            $table->string('carrier');
            $table->string('flight_number');
            $table->integer('duration');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_way_segments');
    }
};
