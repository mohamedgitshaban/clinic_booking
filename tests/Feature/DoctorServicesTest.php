<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_doctors(): void
    {
        Doctor::factory()->count(2)->create();
        Doctor::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/doctors');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'specialization'],
            ]);
    }

    public function test_user_can_view_doctors_services(): void
    {
        $doctor = Doctor::factory()->create(['name' => 'Dr. Ahmed']);
        $activeService = Service::factory()->create(['name' => 'Dental Cleaning', 'price' => 300, 'duration' => 30]);
        $inactiveService = Service::factory()->create(['is_active' => false]);
        $doctor->services()->attach([$activeService->id, $inactiveService->id]);

        $response = $this->getJson("/api/doctors/{$doctor->id}/services");

        $response->assertOk()
            ->assertJson([
                'doctor' => [
                    'id' => $doctor->id,
                    'name' => 'Dr. Ahmed',
                ],
            ])
            ->assertJsonCount(1, 'services')
            ->assertJsonPath('services.0.id', $activeService->id);
    }

    public function test_viewing_services_for_unknown_doctor_returns_not_found(): void
    {
        $response = $this->getJson('/api/doctors/999/services');

        $response->assertNotFound();
    }
}
