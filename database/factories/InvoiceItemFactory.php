<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->randomElement(['Tuition Fee', 'Lab Fee', 'Library Fee', 'Technology Fee', 'Sports Fee']),
            'amount' => fake()->randomElement([500, 800, 1200, 2500, 4000]),
        ];
    }
}
