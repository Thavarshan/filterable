<?php

namespace Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class MockFilterableFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MockFilterable::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'status' => $this->faker->randomElement(['active', 'inactive', 'pending']),
            'age' => $this->faker->numberBetween(18, 65),
            'is_visible' => true,
        ];
    }
}
