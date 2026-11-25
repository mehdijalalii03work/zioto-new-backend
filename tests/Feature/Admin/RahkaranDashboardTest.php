<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\RahkaranDashboard;
use App\Models\HesabfaSyncLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class RahkaranDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_stock_sync_shows_partial_success_and_product_errors(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        HesabfaSyncLog::create([
            'sync_type' => 'rahkaran_stock_sync',
            'status' => 'failed',
            'response_data' => [
                'updated_product_count' => 72,
                'synced_serial_count' => 1726,
                'errors' => ['ZPGB5-00200-0' => 'کالا در پاسخ پیدا نشد.'],
            ],
        ]);

        Livewire::actingAs($admin, 'web')
            ->test(RahkaranDashboard::class)
            ->assertSee('موفقیت جزئی')
            ->assertSee('72 کالا به‌روز شد · 1 کالای خطادار · 1726 سریال خوانده شد')
            ->assertSee('جزئیات خطا')
            ->assertSee('ZPGB5-00200-0');
    }

    public function test_product_counts_include_published_private_products_but_exclude_drafts(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create()->assignRole(Role::Admin->value);

        Product::create([
            'name' => 'منتج عمومی سریالی',
            'slug' => 'public-serial-product',
            'sku' => 'PUBLIC-1',
            'status' => 'published',
            'visibility' => 'public',
            'rahkaran_serial_tracked' => true,
            'price' => 1000,
        ]);
        Product::create([
            'name' => 'محصول خصوصی منتشرشده غیرسریالی',
            'slug' => 'private-published-product',
            'sku' => 'PRIVATE-1',
            'status' => 'published',
            'visibility' => 'private',
            'rahkaran_serial_tracked' => false,
            'price' => 1000,
        ]);
        Product::create([
            'name' => 'پیش‌نویس خصوصی',
            'slug' => 'private-draft-product',
            'sku' => 'DRAFT-1',
            'status' => 'draft',
            'visibility' => 'private',
            'rahkaran_serial_tracked' => false,
            'price' => 1000,
        ]);

        Livewire::actingAs($admin, 'web')
            ->test(RahkaranDashboard::class)
            ->assertSee('1 / 1')
            ->assertDontSee('0 / 1');
    }
}
