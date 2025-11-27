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
        Schema::create('command_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aero_token_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('command');
            $table->text('result');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_requests');
    }
};
