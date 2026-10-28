<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyRequestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $province = Province::first() ?? Province::create(['id' => 1, 'name' => 'تهران', 'slug' => 'tehran']);
        $city = City::where('province_id', $province->id)->first()
            ?? City::create(['province_id' => $province->id, 'name' => 'تهران', 'slug' => 'tehran-city']);

        return array_merge([
            'first_name' => 'علی',
            'last_name' => 'محمدی',
            'birth_date' => '1370/05/12',
            'email' => 'ali@example.com',
            'phone' => '02186038432',
            'mobile' => '09121234567',
            'father_name' => 'رضا',
            'father_job' => 'آزاد',
            'gender' => 'male',
            'marital_status' => 'single',
            'agency_province_id' => $province->id,
            'agency_city_id' => $city->id,
            'residence_province_id' => $province->id,
            'residence_city_id' => $city->id,
            'neighborhood' => 'ولیعصر',
            'address' => 'تهران، میدان ولیعصر، مجتمع الماس کریمخان',
        ], $overrides);
    }

    public function test_store_creates_agency_request(): void
    {
        $response = $this->postJson('/api/agency-requests', $this->payload());

        $response->assertStatus(201);
        $response->assertJson(['message' => 'درخواست نمایندگی شما با موفقیت ثبت شد']);

        $this->assertDatabaseHas('agency_requests', [
            'first_name' => 'علی',
            'last_name' => 'محمدی',
            'email' => 'ali@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/agency-requests', []);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'VALIDATION_FAILED']);
        $this->assertDatabaseCount('agency_requests', 0);
    }

    public function test_store_validates_gender_and_city(): void
    {
        $response = $this->postJson('/api/agency-requests', $this->payload([
            'gender' => 'other',
            'agency_city_id' => 999999,
        ]));

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'VALIDATION_FAILED']);
        $this->assertDatabaseCount('agency_requests', 0);
    }
}
