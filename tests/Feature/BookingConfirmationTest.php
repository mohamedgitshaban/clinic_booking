<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingConfirmationTest extends TestCase
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

    public function test_user_can_confirm_a_booking(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertCreated()->assertJson([
            'doctor' => 'Dr. Ahmed',
            'services' => ['Dental Cleaning', 'Consultation'],
            'total_price' => '800.00',
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_confirming_a_booking_sends_the_user_a_notification(): void
    {
        Notification::fake();

        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ])->assertCreated();

        Notification::assertSentTo($user, BookingConfirmed::class);
    }

    public function test_confirming_a_booking_stores_a_database_notification(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => BookingConfirmed::class,
        ]);
    }

    public function test_confirming_a_booking_recomputes_the_price_from_the_database(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
            // A client-supplied price must never be trusted.
            'total_price' => 1,
        ]);

        $response->assertCreated()->assertJsonPath('total_price', '800.00');
    }

    public function test_guest_cannot_confirm_a_booking(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();

        $response = $this->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_cannot_confirm_a_service_not_offered_by_the_doctor(): void
    {
        [$doctor] = $this->scheduledDoctorWithServices();
        $otherService = Service::factory()->create();
        $user = User::factory()->create();
        $date = Carbon::parse('next monday');

        $response = $this->actingAs($user)->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'service_ids' => [$otherService->id],
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('service_ids');
    }

    public function test_user_cannot_double_book_the_same_slot(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        $payload = [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ];

        $this->actingAs($user)->postJson('/api/bookings', $payload)->assertCreated();
        $response = $this->actingAs($user)->postJson('/api/bookings', $payload);

        $response->assertStatus(409);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_user_cannot_book_a_slot_that_overlaps_an_existing_booking(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctorWithServices();
        $user = User::factory()->create();

        Booking::factory()->create([
            'doctor_id' => $doctor->id,
            'date' => $date->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $response = $this->actingAs($user)->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'service_ids' => $services->pluck('id')->all(),
            'date' => $date->format('Y-m-d'),
            'time' => '10:30',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('bookings', 1);
    }
}
