<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingServiceTest extends TestCase
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

        $services = collect([
            Service::factory()->create(['price' => 300, 'duration' => 30]),
            Service::factory()->create(['price' => 500, 'duration' => 60]),
        ]);
        $doctor->services()->attach($services->pluck('id'));

        return [$doctor, $services, $date];
    }

    public function test_it_creates_a_confirmed_booking_with_computed_price_and_duration(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();

        $booking = app(BookingService::class)->book($user, $doctor, $services, $date->format('Y-m-d'), '09:00');

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame('800.00', $booking->total_price);
        $this->assertSame('09:00:00', $booking->start_time);
        $this->assertSame('10:30:00', $booking->end_time);
        $this->assertCount(2, $booking->services);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_it_prevents_double_booking_the_exact_same_slot(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();

        app(BookingService::class)->book($user, $doctor, $services, $date->format('Y-m-d'), '09:00');

        $this->expectException(SlotUnavailableException::class);

        app(BookingService::class)->book($user, $doctor, $services, $date->format('Y-m-d'), '09:00');
    }

    public function test_it_prevents_booking_a_slot_that_overlaps_an_existing_booking(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();

        // 09:00-10:30 (services total 90 minutes)
        app(BookingService::class)->book($user, $doctor, $services, $date->format('Y-m-d'), '09:00');

        $this->expectException(SlotUnavailableException::class);

        // 10:00 would overlap the 09:00-10:30 booking above.
        app(BookingService::class)->book($user, $doctor, $services, $date->format('Y-m-d'), '10:00');
    }

    public function test_it_allows_booking_a_different_non_overlapping_slot(): void
    {
        [$doctor, $services, $date] = $this->scheduledDoctor();
        $user = User::factory()->create();

        app(BookingService::class)->book($user, $doctor, $services, $date->format('Y-m-d'), '09:00');
        $second = app(BookingService::class)->book($user, $doctor, $services, $date->format('Y-m-d'), '10:30');

        $this->assertSame('10:30:00', $second->start_time);
        $this->assertDatabaseCount('bookings', 2);
    }
}
