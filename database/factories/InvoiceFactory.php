<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomElement([3200, 4500, 5200, 6400]);

        return [
            'student_id' => Student::factory(),
            'term_id' => AcademicTerm::factory(),
            'number' => 'INV-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'issue_date' => now()->startOfMonth(),
            'due_date' => now()->addDays(30),
            'total' => $total,
            'status' => 'unpaid',
        ];
    }

    /**
     * Indicate that the invoice is fully paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    /**
     * Indicate that the invoice is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'due_date' => now()->subDays(10),
        ]);
    }
}
