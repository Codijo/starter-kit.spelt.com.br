<?php

namespace Database\Factories\Core\Account;

use App\Enums\Status\Core\AccountStatus;
use App\Models\Core\Account\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'spelt_tenant_id' => (string) Str::ulid(),   // 26 chars — espelha um Tenant do Spelt
            'name' => $this->faker->company(),
            'status' => AccountStatus::ACTIVE,
            'provisioning_status' => 'provisioned',
        ];
    }
}
