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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 10)->nullable();
            $table->nullableMorphs('owner');
            $table->unsignedInteger('user_id')->default(0);
            // $table->string('type');
            $table->string('status');
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('contact_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_tax_number')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('contact_address')->nullable();
            $table->string('contact_city')->nullable();
            $table->string('contact_zip_code')->nullable();
            $table->string('contact_state')->nullable();
            $table->string('contact_country')->nullable();
            $table->unsignedInteger('parent_id')->default(0);
            // $table->string('created_from', 100)->nullable();
            // $table->unsignedInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
