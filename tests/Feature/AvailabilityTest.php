<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledDoctor(int $durationMinutes): array
    {
        $date = Carbon::parse('next monday');

        $doctor = Doctor::factory()->create();
        DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $service = Service::factory()->create(['duration' => $durationMinutes]);
        $doctor->services()->attach($service);

        return [$doctor, $service, $date];
    }

    public function test_user_can_see_available_slots(): void
    {
        [$doctor, $service, $date] = $this->scheduledDoctor(90);

        $response = $this->getJson(
            "/api/doctors/{$doctor->id}/available-slots?date={$date->format('Y-m-d')}&service_ids[]={$service->id}"
        );

        $response->assertOk()
            ->assertJson(['date' => $date->format('Y-m-d')])
            ->assertJsonPath('slots', ['09:00', '10:30', '12:00', '13:30', '15:00']);
    }

    public function test_booked_slot_is_not_available(): void
    {
        [$doctor, $service, $date] = $this->scheduledDoctor(90);

        Booking::factory()->create([
            'doctor_id' => $doctor->id,
            'date' => $date->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'status' => BookingStatus::Confirmed,
        ]);

        Booking::factory()->create([
            'doctor_id' => $doctor->id,
            'date' => $date->format('Y-m-d'),
            'start_time' => '12:00:00',
            'end_time' => '13:30:00',
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->getJson(
            "/api/doctors/{$doctor->id}/available-slots?date={$date->format('Y-m-d')}&service_ids[]={$service->id}"
        );

        $response->assertOk()
            ->assertJsonPath('slots', ['10:30', '13:30', '15:00']);
    }

    public function test_cancelled_bookings_do_not_block_a_slot(): void
    {
        [$doctor, $service, $date] = $this->scheduledDoctor(90);

        Booking::factory()->create([
            'doctor_id' => $doctor->id,
            'date' => $date->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'status' => BookingStatus::Cancelled,
        ]);

        $response = $this->getJson(
            "/api/doctors/{$doctor->id}/available-slots?date={$date->format('Y-m-d')}&service_ids[]={$service->id}"
        );

        $response->assertOk()
            ->assertJsonPath('slots', ['09:00', '10:30', '12:00', '13:30', '15:00']);
    }

    public function test_no_slots_when_doctor_has_no_schedule_for_that_day(): void
    {
        $doctor = Doctor::factory()->create();
        $service = Service::factory()->create(['duration' => 30]);
        $doctor->services()->attach($service);

        $date = Carbon::parse('next monday');

        $response = $this->getJson(
            "/api/doctors/{$doctor->id}/available-slots?date={$date->format('Y-m-d')}&service_ids[]={$service->id}"
        );

        $response->assertOk()->assertJsonPath('slots', []);
    }

    public function test_cannot_request_slots_for_a_service_not_offered_by_the_doctor(): void
    {
        [$doctor] = $this->scheduledDoctor(30);
        $otherService = Service::factory()->create();

        $date = Carbon::parse('next monday');

        $response = $this->getJson(
            "/api/doctors/{$doctor->id}/available-slots?date={$date->format('Y-m-d')}&service_ids[]={$otherService->id}"
        );

        $response->assertUnprocessable()->assertJsonValidationErrors('service_ids');
    }
}
