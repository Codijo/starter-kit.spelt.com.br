<?php

namespace App\Console\Commands\Dev;

use App\Enums\Status\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;
use App\Services\Billing\CreditService;
use Illuminate\Console\Command;

/**
 * DEV: cria uma conta + assinatura ativa + usuário e imprime um token Sanctum, para
 * testar o Platform sem o fluxo SSO real (`/auth/dev-login?token=...`). Aborta em produção.
 */
class TokenCommand extends Command
{
    protected $signature = 'dev:token
        {--seats=5 : Valor do entitlement seats}
        {--credits=100 : Créditos consumíveis (para testar features gated por crédito)}
        {--name= : Nome da conta}
        {--email= : E-mail do usuário}';

    protected $description = 'DEV: cria conta+assinatura+usuário e imprime um token de acesso.';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Indisponível em produção.');

            return self::FAILURE;
        }

        $account = Account::factory()->create(
            $this->option('name') ? ['name' => $this->option('name')] : []
        );

        Subscription::create([
            'account_id' => $account->id,
            'status' => SubscriptionStatus::ACTIVE,
            'has_access' => true,
            'entitlements' => ['seats' => (int) $this->option('seats')],
        ]);

        $credits = (int) $this->option('credits');
        if ($credits > 0) {
            app(CreditService::class)->grant($account, $credits);
        }

        $user = User::factory()->create(array_filter([
            'account_id' => $account->id,
            'email' => $this->option('email'),
        ]));

        $token = $user->createToken('dev')->plainTextToken;

        $this->info("Conta:    {$account->name} ({$account->id})");
        $this->info("Usuário:  {$user->email}");
        $this->info("Créditos: {$account->creditBalance()}");
        $this->newLine();
        $this->line('<comment>Token:</comment>');
        $this->line($token);
        $this->newLine();
        $this->line('<comment>Testar no Platform:</comment>');
        $this->line(config('services.product.platform_url').'/auth/dev-login?token='.$token);

        return self::SUCCESS;
    }
}
