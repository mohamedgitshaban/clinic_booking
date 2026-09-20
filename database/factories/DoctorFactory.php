<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr. '.fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'specialization' => fake()->randomElement(['Dentist', 'Dermatologist', 'Cardiologist', 'Pediatrician', 'Orthopedist']),
            'bio' => fake()->paragraph(),
            'is_active' => true,
        ];
    }
}
