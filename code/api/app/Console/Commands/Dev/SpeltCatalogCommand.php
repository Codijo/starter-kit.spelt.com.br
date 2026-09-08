<?php

namespace App\Console\Commands\Dev;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * DEV: provisiona no Spelt o catálogo comercial do produto — categoria, tipo de crédito,
 * features de capacidade e degraus de plano com seus entitlements e franquias.
 *
 * Existe porque o Spelt é a fonte de verdade de plano/entitlement/crédito (spec §9): sem
 * catálogo lá, o produto não tem o que sincronizar e nada do fluxo comercial é testável.
 * É o passo que antecede o `dev:spelt-purchase` (que cria o cliente e compra um destes planos).
 *
 * GENÉRICO: toda a definição vem de `config/spelt-catalog.php`. Ao copiar o kit para um produto
 * novo, edite só o config — este command não muda.
 *
 * IDEMPOTENTE: identifica tudo por slug. Rodar de novo atualiza valores em vez de duplicar,
 * então serve tanto para semear do zero quanto para recalibrar os números dos planos.
 *
 * Requer SPELT_API_URL + SPELT_API_KEY no .env. Aborta em produção.
 */
class SpeltCatalogCommand extends Command
{
    protected $signature = 'dev:spelt-catalog
        {--dry : Só mostra o que seria criado/atualizado, sem escrever no Spelt}';

    protected $description = 'DEV: cria/atualiza no Spelt a categoria, o tipo de crédito, as features e os planos do produto.';

    private bool $dry = false;

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Indisponível em produção.');

            return self::FAILURE;
        }

        if ((string) config('services.spelt.url') === '' || (string) config('services.spelt.api_key') === '') {
            $this->error('SPELT_API_URL e SPELT_API_KEY precisam estar preenchidos no .env.');

            return self::FAILURE;
        }

        $catalog = config('spelt-catalog');
        if (! is_array($catalog) || ($catalog['plans'] ?? []) === []) {
            $this->error('config/spelt-catalog.php não define nenhum plano. Preencha o catálogo do produto.');

            return self::FAILURE;
        }

        $this->dry = (bool) $this->option('dry');
        if ($this->dry) {
            $this->warn('DRY RUN — nada será escrito no Spelt.');
        }

        // 1) Categoria — é o que faz o produto aparecer no portal Customer.
        $categoryId = $this->upsertCategory($catalog['category'] ?? null);
        if ($categoryId === false) {
            return self::FAILURE;
        }

        // 2) Tipo de crédito (opcional: produtos só-capacidade não têm).
        $creditTypeId = $this->upsertCreditType($catalog['credit_type'] ?? null);
        if ($creditTypeId === false) {
            return self::FAILURE;
        }

        // 3) Features de capacidade.
        $featureIds = $this->upsertFeatures($catalog['features'] ?? []);
        if ($featureIds === null) {
            return self::FAILURE;
        }

        // 4) Planos + vínculos.
        $trial = $catalog['trial'] ?? ['days' => 0, 'credits' => 0];
        $rows = [];

        foreach ($catalog['plans'] as $slug => $plan) {
            $planId = $this->upsertPlan($slug, $plan, $categoryId, (int) ($trial['days'] ?? 0));
            if ($planId === null) {
                return self::FAILURE;
            }

            foreach (($plan['features'] ?? []) as $featureSlug => $value) {
                if (! isset($featureIds[$featureSlug])) {
                    $this->warn("    feature desconhecida no plano {$slug}: {$featureSlug}");

                    continue;
                }
                $this->attachFeature($planId, $featureIds[$featureSlug], (string) $value);
            }

            if ($creditTypeId && ($plan['credits'] ?? 0) > 0) {
                $this->attachCreditGrant($planId, $creditTypeId, (int) $plan['credits'], $trial);
            }

            $rows[] = [
                $plan['name'] ?? $slug,
                'R$ '.number_format((float) ($plan['price'] ?? 0), 2, ',', '.'),
                ($plan['credits'] ?? 0).'/ciclo',
                $plan['note'] ?? '—',
            ];
        }

        $this->newLine();
        $this->table(['Plano', 'Preço', 'Créditos', 'Observação'], $rows);

        if (($trial['days'] ?? 0) > 0) {
            $this->line(sprintf('  Trial: %d dias / %d créditos.', $trial['days'], $trial['credits'] ?? 0));
        }
        if ($url = ($catalog['category']['app_url'] ?? null)) {
            $this->line("  Acesso do cliente: <fg=cyan>{$url}</>");
        }

        $this->newLine();
        $this->info('Catálogo pronto. Próximo passo: php artisan dev:spelt-purchase');

        return self::SUCCESS;
    }

    /** @return string|null|false id da categoria, null se não configurada, false em erro */
    private function upsertCategory(?array $category): string|null|false
    {
        if (! $category) {
            $this->warn('  ! sem categoria configurada — o produto NÃO aparecerá no portal Customer.');

            return null;
        }

        if (($category['app_url'] ?? null) === null) {
            $this->warn('  ! categoria sem app_url (PRODUCT_PLATFORM_URL vazio) — o card aparece mas sem link de acesso.');
        }

        $payload = array_filter([
            'name' => $category['name'] ?? null,
            'slug' => $category['slug'] ?? null,
            'description' => $category['description'] ?? null,
            'color' => $category['color'] ?? null,
            'app_url' => $category['app_url'] ?? null,
            'icon_url' => $category['icon_url'] ?? null,
            'group_name' => $category['group_name'] ?? null,
            'status' => 'active',
        ], fn ($v) => $v !== null);

        $payload['show_on_dashboard'] = (bool) ($category['show_on_dashboard'] ?? true);
        $payload['dashboard_order'] = (int) ($category['dashboard_order'] ?? 0);

        $existing = $this->findBySlug('plan-category', $category['slug'] ?? '');
        if ($existing) {
            $updated = $this->put("plan-category/{$existing['id']}", $payload);
            if ($updated === null && ! $this->dry) {
                return false;
            }
            $this->line("  ~ categoria <fg=cyan>{$category['slug']}</> (atualizada)");

            return $existing['id'];
        }

        $created = $this->post('plan-category', $payload);
        if ($created === null) {
            return $this->dry ? null : false;
        }

        $this->line("  + categoria <fg=green>{$category['slug']}</>");

        return $created['id'];
    }

    /** @return string|null|false */
    private function upsertCreditType(?array $creditType): string|null|false
    {
        if (! $creditType) {
            $this->line('  = sem tipo de crédito (produto só-capacidade)');

            return null;
        }

        $existing = $this->findBySlug('credit-type', $creditType['slug']);
        if ($existing) {
            $this->line("  = credit-type <fg=cyan>{$creditType['slug']}</> (já existe)");

            return $existing['id'];
        }

        $created = $this->post('credit-type', $creditType + ['status' => 'active']);
        if ($created === null) {
            return $this->dry ? 'dry-credit-type' : false;
        }

        $this->line("  + credit-type <fg=green>{$creditType['slug']}</>");

        return $created['id'];
    }

    /** @return array<string,string>|null slug => id */
    private function upsertFeatures(array $features): ?array
    {
        $ids = [];
        foreach ($features as $slug => $feature) {
            $existing = $this->findBySlug('plan-feature', $slug);
            if ($existing) {
                $ids[$slug] = $existing['id'];
                $this->line("  = feature <fg=cyan>{$slug}</> (já existe)");

                continue;
            }

            $created = $this->post('plan-feature', [
                'slug' => $slug,
                'name' => $feature['name'] ?? $slug,
                'description' => $feature['description'] ?? null,
                'default_value' => 0,
                'status' => 'active',
            ]);

            if ($created === null) {
                if (! $this->dry) {
                    return null;
                }
                $created = ['id' => 'dry-'.$slug];
            }

            $ids[$slug] = $created['id'];
            $this->line("  + feature <fg=green>{$slug}</>");
        }

        return $ids;
    }

    private function upsertPlan(string $slug, array $plan, ?string $categoryId, int $trialDays): ?string
    {
        $payload = array_filter([
            'name' => $plan['name'] ?? $slug,
            'slug' => $slug,
            'description' => $plan['description'] ?? null,
            'plan_category_id' => $categoryId,
            'pricing' => ['monthly' => [
                'price' => (float) ($plan['price'] ?? 0),
                'discount_percentage' => 0,
                'active' => true,
                'popular' => (bool) ($plan['popular'] ?? false),
            ]],
            'default_price' => (float) ($plan['price'] ?? 0),
            'default_recurrence' => 'monthly',
            'trial_days' => $trialDays,
            'setup_fee' => 0,
            'status' => 'active',
            'visibility' => 'public',
        ], fn ($v) => $v !== null);

        $existing = $this->findBySlug('plan', $slug);
        if ($existing) {
            $this->put("plan/{$existing['id']}", $payload);
            $this->line("  ~ plano <fg=cyan>{$slug}</> (atualizado)");

            return $existing['id'];
        }

        $created = $this->post('plan', $payload);
        if ($created === null) {
            return $this->dry ? 'dry-'.$slug : null;
        }

        $this->line("  + plano <fg=green>{$slug}</>");

        return $created['id'];
    }

    private function attachFeature(string $planId, string $featureId, string $value): void
    {
        $this->post("plan/{$planId}/feature", [
            'plan_feature_id' => $featureId,
            'custom_value' => $value,
            'status' => 'active',
        ]);
    }

    private function attachCreditGrant(string $planId, string $creditTypeId, int $quantity, array $trial): void
    {
        // O endpoint de credit-grant não tem update: sem esta checagem, rodar de novo duplicaria
        // a franquia e o cliente receberia crédito dobrado no próximo ciclo.
        foreach ($this->get("plan/{$planId}/credit-grant") ?? [] as $grant) {
            if (($grant['credit_type']['id'] ?? $grant['credit_type_id'] ?? null) === $creditTypeId) {
                return;
            }
        }

        $payload = [
            'credit_type_id' => $creditTypeId,
            'quantity' => $quantity,
            'status' => 'active',
        ];

        if (($trial['credits'] ?? 0) > 0) {
            $payload['trial_mode'] = 'fixed';
            $payload['trial_value'] = (int) $trial['credits'];
        }

        $this->post("plan/{$planId}/credit-grant", $payload);
    }

    // ---------------------------------------------------------------- HTTP

    private function findBySlug(string $resource, string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        foreach ($this->get($resource) ?? [] as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<int,array>|null */
    private function get(string $path): ?array
    {
        $response = $this->http()->get($this->url($path));
        if ($response->failed()) {
            return null;
        }

        return $response->json('data.data') ?? $response->json('data') ?? [];
    }

    private function post(string $path, array $body): ?array
    {
        if ($this->dry) {
            $this->line("    [dry] POST {$path}");

            return null;
        }

        $response = $this->http()->post($this->url($path), $body);
        if ($response->failed()) {
            $this->error("    POST {$path} → HTTP {$response->status()} {$response->body()}");

            return null;
        }

        return $response->json('data') ?? [];
    }

    private function put(string $path, array $body): ?array
    {
        if ($this->dry) {
            $this->line("    [dry] PUT {$path}");

            return null;
        }

        $response = $this->http()->put($this->url($path), $body);
        if ($response->failed()) {
            $this->error("    PUT {$path} → HTTP {$response->status()} {$response->body()}");

            return null;
        }

        return $response->json('data') ?? [];
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('services.spelt.api_key'))
            ->acceptJson()
            ->timeout(20);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.spelt.url'), '/')
            .'/api/seller/external/v1/'.ltrim($path, '/');
    }
}
