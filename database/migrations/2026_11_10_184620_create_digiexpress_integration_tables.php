<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('digiexpress_cities')) {
            Schema::create('digiexpress_cities', function (Blueprint $table) {
                $table->string('external_id', 50)->primary();
                $table->string('name', 150);
                $table->string('state_id', 50)->nullable();
                $table->string('state_name', 150)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
                $table->index(['state_name', 'name']);
            });
        }

        if (! Schema::hasTable('digiexpress_city_mappings')) {
            Schema::create('digiexpress_city_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('city_id')->nullable()->unique()->constrained('cities')->nullOnDelete();
                $table->string('digiexpress_city_id', 50)->unique();
                $table->foreign('digiexpress_city_id')->references('external_id')->on('digiexpress_cities')->cascadeOnUpdate()->cascadeOnDelete();
                $table->foreignId('suggested_city_id')->nullable()->constrained('cities')->nullOnDelete();
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_destination_enabled')->default(false);
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->index(['is_verified', 'is_destination_enabled'], 'dx_city_map_verified_enabled_idx');
            });
        }
        if (! Schema::hasIndex('digiexpress_city_mappings', 'dx_city_map_verified_enabled_idx')) {
            Schema::table('digiexpress_city_mappings', function (Blueprint $table) {
                $table->index(['is_verified', 'is_destination_enabled'], 'dx_city_map_verified_enabled_idx');
            });
        }

        Schema::table('order_shippings', function (Blueprint $table) {
            if (! Schema::hasColumn('order_shippings', 'carrier_code')) {
                $table->string('carrier_code', 50)->nullable()->after('shipping_method_name');
            }
            if (! Schema::hasColumn('order_shippings', 'provider_status')) {
                $table->string('provider_status', 100)->nullable()->after('carrier_code');
            }
            if (! Schema::hasColumn('order_shippings', 'shipping_status')) {
                $table->string('shipping_status', 50)->nullable()->after('provider_status');
            }
            if (! Schema::hasColumn('order_shippings', 'provider_status_updated_at')) {
                $table->timestamp('provider_status_updated_at')->nullable()->after('shipping_status');
            }
            if (! Schema::hasColumn('order_shippings', 'provider_shipment_id')) {
                $table->string('provider_shipment_id', 100)->nullable()->after('provider_status')->index('order_shippings_dx_shipment_idx');
            }
            if (! Schema::hasColumn('order_shippings', 'provider_reference_id')) {
                $table->string('provider_reference_id', 50)->nullable()->after('provider_shipment_id')->index('order_shippings_dx_reference_idx');
            }
            if (! Schema::hasColumn('order_shippings', 'provider_error')) {
                $table->text('provider_error')->nullable()->after('provider_reference_id');
            }
            if (! Schema::hasColumn('order_shippings', 'estimated_capacity_weight_grams')) {
                $table->string('estimated_capacity_weight_grams', 30)->nullable()->after('provider_error');
            }
            if (! Schema::hasColumn('order_shippings', 'estimated_capacity_dimensions_cm')) {
                $table->json('estimated_capacity_dimensions_cm')->nullable()->after('estimated_capacity_weight_grams');
            }
        });

        if (! Schema::hasTable('digiexpress_parcels')) {
            Schema::create('digiexpress_parcels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_shipping_id')->constrained('order_shippings')->cascadeOnDelete();
                $table->string('consignor_reference_id', 100);
                $table->string('provider_parcel_id', 100)->nullable()->index();
                $table->string('parcel_code', 100)->nullable()->index();
                $table->unsignedInteger('weight_grams');
                $table->json('dimensions_cm');
                $table->string('status', 100)->nullable();
                $table->string('label_path', 500)->nullable();
                $table->timestamps();
                $table->unique(['order_shipping_id', 'consignor_reference_id'], 'dx_parcel_shipping_ref_uq');
            });
        }
        if (! Schema::hasIndex('digiexpress_parcels', 'dx_parcel_shipping_ref_uq')) {
            Schema::table('digiexpress_parcels', function (Blueprint $table) {
                $table->unique(['order_shipping_id', 'consignor_reference_id'], 'dx_parcel_shipping_ref_uq');
            });
        }

        if (! Schema::hasTable('digiexpress_webhook_events')) {
            Schema::create('digiexpress_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->char('fingerprint', 64)->unique();
                $table->string('shipping_service_order_id', 100)->nullable()->index();
                $table->string('shipping_service_shipment_id', 100)->nullable()->index();
                $table->string('shipment_consignor_reference_id', 100)->nullable()->index();
                $table->string('shipping_service_parcel_id', 100)->nullable()->index();
                $table->string('parcel_shipping_code', 100)->nullable()->index();
                $table->string('shipment_status', 100)->nullable();
                $table->string('parcel_status', 100)->nullable();
                $table->string('progress_type', 150)->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->json('payload');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index(['processed_at', 'created_at']);
            });
        }

        $this->upsertGenericShippingMethod();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digiexpress_webhook_events');
        Schema::dropIfExists('digiexpress_parcels');
        Schema::table('order_shippings', function (Blueprint $table) {
            $table->dropColumn([
                'carrier_code', 'provider_status', 'shipping_status', 'provider_status_updated_at', 'provider_shipment_id', 'provider_reference_id',
                'provider_error', 'estimated_capacity_weight_grams', 'estimated_capacity_dimensions_cm',
            ]);
        });
        Schema::dropIfExists('digiexpress_city_mappings');
        Schema::dropIfExists('digiexpress_cities');
        DB::table('shipping_methods')->whereIn('code', ['tipax', 'post_precious'])->update(['is_active' => true]);
        // Preserve shipping method and rate rows because historical orders may reference them.
        DB::table('shipping_methods')->where('code', 'digiexpress_address')->update(['is_active' => false]);
    }

    private function upsertGenericShippingMethod(): void
    {
        DB::table('shipping_methods')->whereIn('code', ['tipax', 'post_precious'])->update(['is_active' => false]);

        DB::table('shipping_methods')->updateOrInsert(
            ['code' => 'digiexpress_address'],
            [
                'id' => 5,
                'name' => 'ارسال به آدرس',
                'description' => 'ارسال به نشانی ثبت‌شده',
                'icon' => 'heroicon-o-truck',
                'is_active' => true,
                'is_pickup' => false,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        if (! DB::table('shipping_rates')->where('shipping_method_id', 5)->exists()) {
            DB::table('shipping_rates')->where('shipping_method_id', 2)->get()->each(function (object $rate): void {
                $attributes = (array) $rate;
                unset($attributes['id']);
                $attributes['shipping_method_id'] = 5;
                $attributes['created_at'] = now();
                $attributes['updated_at'] = now();
                DB::table('shipping_rates')->insert($attributes);
            });
        }
    }
};
