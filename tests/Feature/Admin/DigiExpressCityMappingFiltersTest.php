<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\DigiExpressCityMappings\Pages\ListDigiExpressCityMappings;
use App\Models\City;
use App\Models\DigiExpressCity;
use App\Models\DigiExpressCityMapping;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DigiExpressCityMappingFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_distinguish_unmapped_cities_from_cities_without_suggestions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create()->assignRole(Role::Admin->value));

        $city = City::factory()->create();
        foreach (['1', '2', '3'] as $id) {
            DigiExpressCity::query()->create(['external_id' => $id, 'name' => 'City '.$id]);
        }

        $unmapped = DigiExpressCityMapping::query()->create(['digiexpress_city_id' => '1']);
        $suggested = DigiExpressCityMapping::query()->create(['digiexpress_city_id' => '2', 'suggested_city_id' => $city->id]);
        $mapped = DigiExpressCityMapping::query()->create(['digiexpress_city_id' => '3', 'city_id' => $city->id]);

        Livewire::test(ListDigiExpressCityMappings::class)
            ->filterTable('unmapped')
            ->assertCanSeeTableRecords([$unmapped, $suggested])
            ->assertCanNotSeeTableRecords([$mapped])
            ->filterTable('without_suggestion')
            ->assertCanSeeTableRecords([$unmapped])
            ->assertCanNotSeeTableRecords([$suggested, $mapped]);

        Livewire::test(ListDigiExpressCityMappings::class)
            ->filterTable('without_suggestion')
            ->assertCanSeeTableRecords([$unmapped, $mapped])
            ->assertCanNotSeeTableRecords([$suggested]);
    }
}
