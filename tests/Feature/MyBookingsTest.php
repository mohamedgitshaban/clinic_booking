<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_his_bookings(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $doctor = Doctor::factory()->create(['name' => 'Dr. Ahmed']);
        $service = Service::factory()->create(['name' => 'Dental Cleaning', 'price' => 300, 'duration' => 30]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'total_price' => 300,
        ]);
        $booking->services()->attach($service->id, ['price' => 300, 'duration' => 30]);

        Booking::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson('/api/my-bookings');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJson([[
                'id' => $booking->id,
                'doctor' => 'Dr. Ahmed',
                'services' => ['Dental Cleaning'],
                'total_price' => '300.00',
                'status' => 'pending',
            ]]);
    }

    public function test_guest_cannot_view_bookings(): void
    {
        $response = $this->getJson('/api/my-bookings');

        $response->assertUnauthorized();
    }
}
