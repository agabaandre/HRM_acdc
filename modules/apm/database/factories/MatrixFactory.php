<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Division;
use App\Models\FocalPerson;
use App\Models\Matrix;
use App\Models\Staff;

class MatrixFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Matrix::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'focal_person_id' => FocalPerson::factory(),
            'division_id' => Division::factory(),
            'year' => fake()->year(),
            'quarter' => fake()->randomElement(["Q1","Q2","Q3","Q4"]),
            'key_result_area' => '{}',
            'staff_id' => Staff::factory(),
        ];
    }
}
