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
        Schema::create('agency_requests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('birth_date', 20);
            $table->string('email');
            $table->string('phone', 20);
            $table->string('mobile', 20);
            $table->string('father_name');
            $table->string('father_job');
            $table->enum('gender', ['male', 'female']);
            $table->enum('marital_status', ['single', 'married']);
            $table->unsignedTinyInteger('agency_province_id');
            $table->foreign('agency_province_id')->references('id')->on('provinces');
            $table->foreignId('agency_city_id')->constrained('cities');
            $table->unsignedTinyInteger('residence_province_id');
            $table->foreign('residence_province_id')->references('id')->on('provinces');
            $table->foreignId('residence_city_id')->constrained('cities');
            $table->string('neighborhood');
            $table->text('address');
            $table->string('status', 20)->default('pending');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_requests');
    }
};
