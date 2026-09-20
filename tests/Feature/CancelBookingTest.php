<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CancelBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_a_booking_outside_the_cutoff_window(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Confirmed,
            'date' => Carbon::today()->addDays(3)->format('Y-m-d'),
            'start_time' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_user_cannot_cancel_a_completed_booking(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Completed,
            'date' => Carbon::today()->addDays(3)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(409);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'completed']);
    }

    public function test_user_cannot_cancel_an_already_cancelled_booking(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Cancelled,
            'date' => Carbon::today()->addDays(3)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(409);
    }

    public function test_user_cannot_cancel_within_the_configured_cutoff_window(): void
    {
        config(['booking.cancellation_hours' => 24]);

        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Confirmed,
            'date' => Carbon::now()->addHours(2)->format('Y-m-d'),
            'start_time' => Carbon::now()->addHours(2)->format('H:i:s'),
        ]);

        $response = $this->actingAs($user)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(409);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'status' => BookingStatus::Confirmed,
            'date' => Carbon::today()->addDays(3)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($otherUser)->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
    }

    public function test_guest_cannot_cancel_a_booking(): void
    {
        $booking = Booking::factory()->create(['doctor_id' => Doctor::factory()]);

        $response = $this->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertUnauthorized();
    }
}
