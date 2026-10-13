<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->supportsChecks()) {
            return;
        }
        DB::statement("ALTER TABLE product_serials
            ADD CONSTRAINT product_serials_valid_status CHECK (status IN ('available','reserved','allocated','shipped','returned','damaged','lost')),
            ADD CONSTRAINT product_serials_coherent_owner CHECK (
                (status = 'available' AND current_order_item_id IS NULL AND unit_number IS NULL)
                OR (status IN ('reserved','allocated','shipped') AND current_order_item_id IS NOT NULL AND unit_number IS NOT NULL AND unit_number > 0)
                OR (status IN ('returned','damaged','lost') AND ((current_order_item_id IS NULL AND unit_number IS NULL) OR (current_order_item_id IS NOT NULL AND unit_number IS NOT NULL AND unit_number > 0)))
            )");
    }

    public function down(): void
    {
        if (! $this->supportsChecks()) {
            return;
        }
        DB::statement('ALTER TABLE product_serials DROP CHECK product_serials_coherent_owner, DROP CHECK product_serials_valid_status');
    }

    private function supportsChecks(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;

        return ! str_contains(strtolower($version), 'mariadb') && version_compare($version, '8.0.16', '>=');
    }
};
