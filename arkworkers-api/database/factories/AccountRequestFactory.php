<?php

namespace Database\Factories;

use App\Models\AccountRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountRequest>
 */
class AccountRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'status' => AccountRequest::STATUS_PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountRequest::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountRequest::STATUS_REJECTED,
            'reviewed_at' => now(),
        ]);
    }
}
