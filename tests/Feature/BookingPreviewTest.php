<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledDoctorWithServices(): array
    {
        $date = Carbon::parse('next monday');

        $doctor = Doctor::factory()->create(['name' => 'Dr. Ahmed']);
        DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $services = collect([
            Service::factory()->create(['name' => 'Dental Cleaning', 'price' => 300, 'duration' => 30]),
            Service::factory()->create(['name' => 'Consultation', 'price' => 500, 'duration' => 60]),
        ]);
        $doctor->services()->attach($services->pluck('id'));

        return [$doctor, $services, $date];
    }

    public function test_user_can_preview_a_booking(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bookings/preview', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertOk()->assertExactJson([
            'doctor' => 'Dr. Ahmed',
            'services' => ['Dental Cleaning', 'Consultation'],
            'total_price' => 800.0,
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_guest_cannot_preview_a_booking(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();

        $response = $this->postJson('/api/bookings/preview', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertUnauthorized();
    }

    public function test_preview_rejects_a_service_not_offered_by_the_doctor(): void
    {
        [$doctor, , $date] = $this->scheduledDoctorWithServices();
        $otherService = Service::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bookings/preview', [
            'doctor_id' => $doctor->id,
            'service_ids' => [$otherService->id],
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('service_ids');
    }

    public function test_preview_rejects_a_date_in_the_past(): void
    {
        [$doctor, $services] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bookings/preview', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => Carbon::yesterday()->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('date');
    }
}
