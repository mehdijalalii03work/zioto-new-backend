<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_shippings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('shipping_method_id')->unsigned()->constrained()->cascadeOnDelete();
            $table->string('shipping_method_name', 100);
            $table->decimal('shipping_cost', 12, 0);
            $table->date('pickup_date')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->tinyInteger('estimated_delivery_min')->nullable();
            $table->tinyInteger('estimated_delivery_max')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shippings');
    }
};
