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
        Schema::table('flight_availablities', function (Blueprint $table) {
            $table->string('aircraft_code')->nullable()->after('fare_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_availablities', function (Blueprint $table) {
            $table->dropColumn('aircraft_code');
        });
    }
};
