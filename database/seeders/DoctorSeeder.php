<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::factory(5)->create();

        Doctor::factory(5)
            ->create()
            ->each(function (Doctor $doctor) use ($services): void {
                $doctor->services()->attach(
                    $services->random(random_int(2, 4))->pluck('id')
                );
            });
    }
}
