<?php

namespace Database\Factories;

use App\Enums\EmailDriver;
use App\Models\EmailProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailProvider>
 */
class EmailProviderFactory extends Factory
{
    protected $model = EmailProvider::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Mail',
            'slug' => Str::slug(fake()->unique()->words(2, true)),
            'driver' => EmailDriver::Exchange,
            'config' => [],
            'from_address' => fake()->companyEmail(),
            'from_name' => fake()->company(),
            'is_default' => false,
            'is_active' => true,
            'priority' => 100,
            'description' => null,
        ];
    }
}
