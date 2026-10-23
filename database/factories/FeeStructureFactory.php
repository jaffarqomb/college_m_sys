<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\FeeStructure;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_id' => AcademicTerm::factory(),
            'program_id' => Program::factory(),
            'name' => fake()->randomElement(['Tuition', 'Registration fee', 'Lab fee']),
            'amount' => fake()->randomElement([2500, 3200, 750, 1200]),
            'is_active' => true,
        ];
    }

    /**
     * A structure that applies to students of every program.
     */
    public function forAllPrograms(): static
    {
        return $this->state(fn (array $attributes) => [
            'program_id' => null,
        ]);
    }

    /**
     * A retired structure that no longer applies to new invoices.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
