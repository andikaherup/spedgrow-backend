<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition()
    {
        return [
            'transaction_id' => 'TXN_' . $this->faker->unique()->randomNumber(8),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'type' => $this->faker->randomElement(['debit', 'credit']),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed']),
            'merchant_name' => $this->faker->company(),
            'category' => $this->faker->randomElement(['food', 'transport', 'shopping', 'entertainment']),
            'nfc_data' => $this->faker->boolean(30) ? [
                'card_id' => $this->faker->creditCardNumber(),
                'terminal_id' => $this->faker->randomNumber(6),
                'signal_strength' => $this->faker->numberBetween(-80, -20)
            ] : null,
            'transaction_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
