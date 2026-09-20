<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->randomElement(['09:00:00', '10:00:00', '11:00:00', '14:00:00', '15:00:00']);

        return [
            'user_id' => User::factory(),
            'doctor_id' => Doctor::factory(),
            'date' => fake()->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => date('H:i:s', strtotime($startTime.' +30 minutes')),
            'total_price' => fake()->randomFloat(2, 100, 1000),
            'status' => BookingStatus::Pending,
        ];
    }
}
