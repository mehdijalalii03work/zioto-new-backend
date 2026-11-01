<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('header_image')->nullable();
            $table->longText('guide_content')->nullable();
            $table->json('previous_slugs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['header_image', 'guide_content', 'previous_slugs']);
        });
    }
};
