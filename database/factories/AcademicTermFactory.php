<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    /**
     * Monotonic suffix keeping generated codes unique for the whole test run,
     * since Spring and Summer share the same first letter.
     */
    private static int $codeSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $season = fake()->randomElement(['Fall', 'Spring', 'Summer']);
        $year = fake()->numberBetween(2025, 2028);
        self::$codeSequence++;

        return [
            'name' => $season.' '.$year,
            'code' => $season[0].$year.'-'.self::$codeSequence,
            'start_date' => Carbon::parse($year.'-09-01'),
            'end_date' => Carbon::parse($year.'-12-20'),
            'registration_deadline' => Carbon::parse($year.'-08-25'),
            'is_current' => false,
        ];
    }

    /**
     * Indicate that the term is the current term.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
            'registration_deadline' => now()->addDays(14),
        ]);
    }
}
