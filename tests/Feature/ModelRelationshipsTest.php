<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_has_many_services(): void
    {
        $doctor = Doctor::factory()->create();
        $services = Service::factory()->count(2)->create();
        $doctor->services()->attach($services);

        $this->assertCount(2, $doctor->refresh()->services);
    }

    public function test_doctor_has_many_bookings(): void
    {
        $doctor = Doctor::factory()->create();
        Booking::factory()->count(2)->create(['doctor_id' => $doctor->id]);

        $this->assertCount(2, $doctor->bookings);
    }

    public function test_service_belongs_to_many_doctors(): void
    {
        $service = Service::factory()->create();
        $doctors = Doctor::factory()->count(2)->create();
        $service->doctors()->attach($doctors);

        $this->assertCount(2, $service->refresh()->doctors);
    }

    public function test_user_has_many_bookings(): void
    {
        $user = User::factory()->create();
        Booking::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->bookings);
    }

    public function test_booking_belongs_to_user_and_doctor(): void
    {
        $user = User::factory()->create();
        $doctor = Doctor::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        $this->assertTrue($booking->user->is($user));
        $this->assertTrue($booking->doctor->is($doctor));
    }

    public function test_booking_belongs_to_many_services_with_pivot_price_and_duration(): void
    {
        $booking = Booking::factory()->create();
        $service = Service::factory()->create(['price' => 300, 'duration' => 30]);

        $booking->services()->attach($service->id, ['price' => 300, 'duration' => 30]);

        $attached = $booking->refresh()->services->first();

        $this->assertSame('300.00', $attached->pivot->price);
        $this->assertSame(30, $attached->pivot->duration);
    }
}
