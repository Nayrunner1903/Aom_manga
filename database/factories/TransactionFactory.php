<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'user_id' => fn (array $attributes): int => Category::query()->whereKey($attributes['category_id'])->firstOrFail()->user_id,
            'type' => fn (array $attributes): string => Category::query()->whereKey($attributes['category_id'])->firstOrFail()->type,
            'amount_satang' => fake()->numberBetween(1, 1000000),
            'description' => fake()->optional()->sentence(),
            'transaction_date' => fake()->date(),
        ];
    }
}
