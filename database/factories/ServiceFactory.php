<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Consultation', 'Dental Cleaning', 'X-Ray', 'Follow-up Visit', 'Vaccination']),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 100, 1000),
            'duration' => fake()->randomElement([15, 30, 45, 60]),
            'is_active' => true,
        ];
    }
}
