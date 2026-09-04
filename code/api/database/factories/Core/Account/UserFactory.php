<?php

namespace Database\Factories\Core\Account;

use App\Enums\Status\Core\UserStatus;
use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'status' => UserStatus::ACTIVE,
            // SSO-only: senha não é usada no fluxo real; valor determinístico só p/ testes.
            'password' => Hash::make('password'),
        ];
    }
}
