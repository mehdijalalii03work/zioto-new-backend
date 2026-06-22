<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->tinyInteger('province_id')->unsigned()->constrained();
            $table->foreignId('city_id')->constrained();
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('address_line');
            $table->string('receiver_name', 100)->nullable();
            $table->string('receiver_phone', 20)->nullable();
            $table->string('receiver_national_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_billing')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index(['user_id', 'is_default']);
            $table->index(['province_id', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
