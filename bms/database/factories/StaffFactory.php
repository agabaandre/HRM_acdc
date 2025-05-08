<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Division;
use App\Models\DutyStation;
use App\Models\Staff;

class StaffFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Staff::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'staff_id' => fake()->numberBetween(-10000, 10000),
            'work_email' => fake()->word(),
            'sap_no' => fake()->word(),
            'title' => fake()->sentence(4),
            'fname' => fake()->word(),
            'lname' => fake()->word(),
            'oname' => fake()->word(),
            'grade' => fake()->word(),
            'gender' => fake()->word(),
            'date_of_birth' => fake()->date(),
            'job_name' => fake()->word(),
            'contracting_institution' => fake()->word(),
            'contract_type' => fake()->word(),
            'nationality' => fake()->word(),
            'division_name' => fake()->word(),
            'division_id' => Division::factory(),
            'duty_station_id' => DutyStation::factory(),
            'status' => fake()->word(),
            'tel_1' => fake()->word(),
            'whatsapp' => fake()->word(),
            'private_email' => fake()->word(),
            'photo' => fake()->word(),
            'physical_location' => fake()->word(),
        ];
    }
}
