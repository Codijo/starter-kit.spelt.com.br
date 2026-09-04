<?php

namespace App\Console\Commands\Dev;

use App\Services\Spelt\SpeltClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * DEV: cria um Customer REAL no Spelt e simula a compra de um plano (test mode), para testar
 * o portal Customer do Spelt de ponta a ponta.
 *
 * Diferente do `dev:token` (que só cria a conta LOCAL no produto, sem existir no Spelt): este
 * cria customer + usuário de login + assinatura + fatura QUITADA no Spelt. Quitar a fatura
 * dispara, no Spelt, a ativação da assinatura + concessão dos créditos do plano + os webhooks
 * de volta ao produto — ou seja, o mesmo caminho de uma compra real.
 *
 * Requer SPELT_API_URL + SPELT_API_KEY no .env. Use uma chave `splt_test_` (test mode) para não
 * gerar cobrança real. Aborta em produção.
 */
class SpeltPurchaseCommand extends Command
{
    protected $signature = 'dev:spelt-purchase
        {plan_id? : ULID do plano no Spelt (omita para listar e escolher)}
        {--recurrence= : Recorrência (default: a default_recurrence do plano)}
        {--name= : Nome do customer/usuário de teste}
        {--email= : E-mail de login do usuário (default: gerado)}
        {--password=Kit-Dev-2026! : Senha de login do usuário}
        {--method=pix : Método de pagamento da fatura}';

    protected $description = 'DEV: cria um Customer no Spelt e simula a compra de um plano (ativa + concede créditos).';

    public function handle(SpeltClient $spelt): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Indisponível em produção.');

            return self::FAILURE;
        }

        $url = (string) config('services.spelt.url');
        $key = (string) config('services.spelt.api_key');
        if ($url === '' || $key === '') {
            $this->error('SPELT_API_URL e SPELT_API_KEY precisam estar preenchidos no .env.');

            return self::FAILURE;
        }
        if (! str_starts_with($key, 'splt_test_')) {
            $this->warn('A chave não é splt_test_ — a compra pode gerar dados/cobrança REAIS no Spelt.');
            if (! $this->confirm('Continuar mesmo assim?', false)) {
                return self::FAILURE;
            }
        }

        // 1) Plano
        $planId = $this->argument('plan_id') ?: $this->pickPlan($spelt);
        if (! $planId) {
            return self::FAILURE;
        }

        $plan = $spelt->getPlan($planId);
        if (! $plan) {
            $this->reportError('Plano não encontrado', $spelt);

            return self::FAILURE;
        }
        $recurrence = $this->option('recurrence') ?: ($plan['default_recurrence'] ?? 'monthly');
        $this->line("Plano: <info>{$plan['name']}</info> · recorrência <info>{$recurrence}</info>");

        // 2) Customer + usuário de login
        $suffix = Str::lower(Str::random(6));
        $name = $this->option('name') ?: "Cliente Teste {$suffix}";
        $email = $this->option('email') ?: "teste+{$suffix}@example.test";
        $password = (string) $this->option('password');

        // status=active: senão o tenant nasce `pending` e o portal barra com "Tenant inativo".
        $customer = $spelt->createCustomer(['name' => $name, 'email' => $email, 'status' => 'active']);
        if (! $customer) {
            $this->reportError('Falha ao criar o customer', $spelt);

            return self::FAILURE;
        }
        $customerId = $customer['id'];
        $this->line("Customer: <info>{$customerId}</info>");

        $user = $spelt->createCustomerUser($customerId, [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
        ]);
        if (! $user) {
            $this->reportError('Falha ao criar o usuário do customer', $spelt);

            return self::FAILURE;
        }

        // 3) Assinatura + fatura
        $sub = $spelt->createSubscription([
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'recurrence_type' => $recurrence,
            'generate_invoice' => true,
            'invoice_payment_method' => (string) $this->option('method'),
        ]);
        if (! $sub) {
            $this->reportError('Falha ao criar a assinatura', $spelt);

            return self::FAILURE;
        }
        $subscriptionId = $sub['id'] ?? null;
        $invoiceId = $sub['invoice_id'] ?? null;

        // 4) Quitar a fatura (test mode) → ativa + concede créditos + dispara webhooks.
        // Valor: prefere o total real da fatura; como o GET /invoice do Spelt pode falhar
        // (bug conhecido: relação `items` inexistente), cai no preço da própria assinatura.
        $paid = false;
        if ($invoiceId) {
            $invoice = $spelt->getInvoice($invoiceId);
            $amount = (float) ($invoice['total_amount'] ?? $sub['total_price'] ?? $sub['final_price'] ?? 0);
            if ($amount > 0) {
                $res = $spelt->payInvoice($invoiceId, $amount, 'DEV: simulação de compra (dev:spelt-purchase)');
                $paid = ($res['status'] ?? null) === 'paid';
                if (! $paid) {
                    $this->reportError('Assinatura criada, mas a fatura não foi quitada', $spelt);
                }
            } else {
                $paid = true; // sem valor a cobrar (trial/grátis)
            }
        }

        // 5) Resumo
        $this->newLine();
        $this->info('Compra simulada no Spelt.');
        $this->table(['Campo', 'Valor'], [
            ['Customer', "{$name} ({$customerId})"],
            ['Login (portal Customer)', $email],
            ['Senha', $password],
            ['Assinatura', ($subscriptionId ?? '—').' · '.($sub['status'] ?? '—')],
            ['Fatura', ($invoiceId ?? '—').($paid ? ' · quitada' : ' · pendente')],
            ['Plano', "{$plan['name']} ({$planId})"],
        ]);
        $this->newLine();
        $this->line('<comment>Testar o portal Customer:</comment> entre com o e-mail/senha acima.');
        $this->line('Ao quitar a fatura, o Spelt concede os créditos do plano e sincroniza o produto via webhook.');

        return self::SUCCESS;
    }

    /** Lista os planos do Seller e deixa o operador escolher pelo número. */
    private function pickPlan(SpeltClient $spelt): ?string
    {
        $plans = $spelt->listPlans();
        if ($plans === null) {
            $this->reportError('Não foi possível listar os planos', $spelt);

            return null;
        }
        if ($plans === []) {
            $this->error('Nenhum plano encontrado para este Seller.');

            return null;
        }

        $rows = [];
        foreach (array_values($plans) as $i => $p) {
            $rows[] = [$i + 1, $p['id'], $p['name'] ?? '—', $p['default_price'] ?? '—', $p['default_recurrence'] ?? '—'];
        }
        $this->table(['#', 'ID', 'Nome', 'Preço', 'Recorrência'], $rows);

        $n = (int) $this->ask('Número do plano', '1');
        $chosen = array_values($plans)[$n - 1] ?? null;

        return $chosen['id'] ?? null;
    }

    /** Imprime o último erro da External API (status + corpo) para depuração. */
    private function reportError(string $message, SpeltClient $spelt): void
    {
        $this->error($message.'.');
        $err = $spelt->lastError();
        if ($err) {
            $this->line('  status: '.($err['status'] ?? '?'));
            $body = $err['body'] ?? null;
            $this->line('  resposta: '.(is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE)));
        }
    }
}
