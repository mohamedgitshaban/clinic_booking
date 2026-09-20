<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_offers_services_when_all_selected_services_belong_to_doctor(): void
    {
        $doctor = Doctor::factory()->create();
        $services = Service::factory()->count(2)->create();
        $doctor->services()->attach($services);

        $this->assertTrue($doctor->offersServices($services->pluck('id')->all()));
    }

    public function test_doctor_does_not_offer_services_when_a_selected_service_belongs_to_another_doctor(): void
    {
        $doctor = Doctor::factory()->create();
        $ownService = Service::factory()->create();
        $doctor->services()->attach($ownService);

        $otherDoctorsService = Service::factory()->create();

        $this->assertFalse($doctor->offersServices([$ownService->id, $otherDoctorsService->id]));
    }

    public function test_doctor_does_not_offer_services_for_an_empty_selection(): void
    {
        $doctor = Doctor::factory()->create();

        $this->assertFalse($doctor->offersServices([]));
    }
}
