<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RescheduleBookingTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledDoctor(): array
    {
        $date = Carbon::parse('next monday');

        $doctor = Doctor::factory()->create();
        DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        return [$doctor, $date];
    }

    private function bookingFor(Doctor $doctor, User $user, Carbon $date, string $startTime, int $durationMinutes = 30): Booking
    {
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'date' => $date->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => Carbon::parse($startTime)->addMinutes($durationMinutes)->format('H:i:s'),
            'status' => BookingStatus::Confirmed,
        ]);

        $service = Service::factory()->create(['duration' => $durationMinutes]);
        $booking->services()->attach($service->id, ['price' => $service->price, 'duration' => $durationMinutes]);

        return $booking;
    }

    public function test_user_can_reschedule_a_booking_to_an_available_slot(): void
    {
        [$doctor, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();
        $booking = $this->bookingFor($doctor, $user, $date, '09:00:00');

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/reschedule", [
            'date' => $date->format('Y-m-d'),
            'time' => '11:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('date', $date->format('Y-m-d'))
            ->assertJsonPath('time', '11:00');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'start_time' => '11:00:00',
        ]);
    }

    public function test_user_can_reschedule_to_the_exact_same_slot(): void
    {
        [$doctor, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();
        $booking = $this->bookingFor($doctor, $user, $date, '09:00:00');

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/reschedule", [
            'date' => $date->format('Y-m-d'),
            'time' => '09:00',
        ]);

        $response->assertOk();
    }

    public function test_user_cannot_reschedule_to_a_slot_occupied_by_another_booking(): void
    {
        [$doctor, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();
        $booking = $this->bookingFor($doctor, $user, $date, '09:00:00');
        $this->bookingFor($doctor, User::factory()->create(), $date, '11:00:00');

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/reschedule", [
            'date' => $date->format('Y-m-d'),
            'time' => '11:00',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'start_time' => '09:00:00']);
    }

    public function test_user_cannot_reschedule_a_completed_booking(): void
    {
        [$doctor, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();
        $booking = $this->bookingFor($doctor, $user, $date, '09:00:00');
        $booking->update(['status' => BookingStatus::Completed]);

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/reschedule", [
            'date' => $date->format('Y-m-d'),
            'time' => '11:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_user_cannot_reschedule_another_users_booking(): void
    {
        [$doctor, $date] = $this->scheduledDoctor();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $booking = $this->bookingFor($doctor, $owner, $date, '09:00:00');

        $response = $this->actingAs($otherUser)->postJson("/api/bookings/{$booking->id}/reschedule", [
            'date' => $date->format('Y-m-d'),
            'time' => '11:00',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_cannot_reschedule_a_booking(): void
    {
        [$doctor, $date] = $this->scheduledDoctor();
        $booking = $this->bookingFor($doctor, User::factory()->create(), $date, '09:00:00');

        $response = $this->postJson("/api/bookings/{$booking->id}/reschedule", [
            'date' => $date->format('Y-m-d'),
            'time' => '11:00',
        ]);

        $response->assertUnauthorized();
    }

    public function test_reschedule_requires_date_and_time(): void
    {
        [$doctor, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();
        $booking = $this->bookingFor($doctor, $user, $date, '09:00:00');

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/reschedule", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date', 'time']);
    }
}
