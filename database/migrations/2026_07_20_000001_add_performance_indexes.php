<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── products ────────────────────────────────────────────────────
        // ProductController::index → orderBy('sort_order')->orderBy('id')
        Schema::table('products', function (Blueprint $table) {
            $table->index('sort_order');
        });

        // ── orders ──────────────────────────────────────────────────────
        // OrderSubmitController::index → where('user_id')->orderBy('created_at','desc')
        // PaymentController::status → where('id')->where('user_id')
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        // HesabfaObserver → where('status', ...)->whereNull('hesabfa_synced_at')
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'hesabfa_synced_at']);
        });

        // ── payments ────────────────────────────────────────────────────
        // PaymentController::callback → where('order_id')->where('gateway')->where('status','pending')->latest()
        // PaymentController::status → where('order_id')->latest()
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['order_id', 'gateway', 'status']);
            $table->index(['order_id', 'created_at']);
        });

        // ── blog_posts ──────────────────────────────────────────────────
        // BlogController::posts → where('status','published')->orderBy('sort_order')->orderByDesc('published_at')
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->index(['status', 'sort_order', 'published_at']);
        });

        // ── blog_categories ─────────────────────────────────────────────
        // BlogController::categories → where('is_active', true)->orderBy('sort_order')
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order']);
        });

        // ── shipping_methods ────────────────────────────────────────────
        // ShippingController → scopeActive: where('is_active')->orderBy('sort_order')
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order']);
        });

        // ── shipping_rates ──────────────────────────────────────────────
        // ShippingController::calculate → where('shipping_method_id')->where('province_id')
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->index(['shipping_method_id', 'province_id']);
        });

        // ── order_notes ─────────────────────────────────────────────────
        // OrderSubmitController::notes → where('order_id')->where('is_customer_note')
        Schema::table('order_notes', function (Blueprint $table) {
            $table->index(['order_id', 'is_customer_note']);
        });

        // ── hesabfa_sync_log ────────────────────────────────────────────
        // HesabfaDashboard → latest() ordering
        Schema::table('hesabfa_sync_log', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_sort_order_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_id_created_at_index');
            $table->dropIndex('orders_status_hesabfa_synced_at_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_order_id_gateway_status_index');
            $table->dropIndex('payments_order_id_created_at_index');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex('blog_posts_status_sort_order_published_at_index');
        });

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropIndex('blog_categories_is_active_sort_order_index');
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropIndex('shipping_methods_is_active_sort_order_index');
        });

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->dropIndex('shipping_rates_shipping_method_id_province_id_index');
        });

        Schema::table('order_notes', function (Blueprint $table) {
            $table->dropIndex('order_notes_order_id_is_customer_note_index');
        });

        Schema::table('hesabfa_sync_log', function (Blueprint $table) {
            $table->dropIndex('hesabfa_sync_log_created_at_index');
        });
    }
};
