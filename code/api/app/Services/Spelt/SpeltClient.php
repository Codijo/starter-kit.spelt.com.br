<?php

namespace App\Services\Spelt;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da External API v1 do Spelt (server-to-server). O produto é CLIENTE do
 * Spelt: lê plano/assinatura, reporta uso e gera o SSO reverso. Ver spec §8.4.
 *
 * Falhas retornam null (o chamador decide o fallback) e são logadas.
 */
class SpeltClient
{
    /** Último erro capturado (status + corpo) — exposto via lastError() para comandos. */
    protected ?array $lastError = null;

    /** Detalha um plano (com features) — usado para o snapshot de entitlements. */
    public function getPlan(string $planId): ?array
    {
        return $this->get("plan/{$planId}");
    }

    /** Detalha uma assinatura — usado pelo reconcile. */
    public function getSubscription(int|string $id): ?array
    {
        return $this->get("subscription/{$id}");
    }

    /**
     * Lista as assinaturas de um customer — a FONTE DA VERDADE do reconcile (status +
     * has_access autoritativos). Devolve a lista (não o paginador).
     */
    public function getCustomerSubscriptions(string $customerId): ?array
    {
        $result = $this->get("customer/{$customerId}/subscription");

        if ($result === null) {
            return null;
        }

        return $result['data'] ?? $result; // aceita paginador {data:[...]} ou lista direta
    }

    /** Lotes de crédito ATIVOS de um customer — usado pelo reconcile para curar grant perdido. */
    public function getCustomerCredits(string $customerId): ?array
    {
        $result = $this->get("credit?customer_id={$customerId}&status=active&per_page=100");

        if ($result === null) {
            return null;
        }

        return $result['data'] ?? $result;
    }

    /** Reporta consumo (usage-based, opt-in). */
    public function reportUsage(array $payload): ?array
    {
        return $this->post('usage-record', $payload);
    }

    /**
     * Reporta a utilização das features de capacidade da assinatura.
     *
     * É o que permite o Spelt bloquear um downgrade que violaria o uso atual (spec §10):
     * o portal Customer compara este snapshot com os limites do plano-alvo e recusa a
     * descida. O mapa SUBSTITUI o snapshot inteiro — mande todas as features de uma vez,
     * não incrementos.
     *
     * @param  array<string,int>  $usage  {"plan-feature-company": 3, "plan-feature-user": 5}
     */
    public function reportFeatureUsage(int|string $subscriptionId, array $usage): ?array
    {
        return $this->put("subscription/{$subscriptionId}/feature-usage", ['feature_usage' => $usage]);
    }

    /** Gera token de SSO reverso (produto → portal Customer do Spelt). */
    public function generateReverseSso(string $customerId): ?array
    {
        return $this->post("customer/{$customerId}/auth-token", []);
    }

    // ── Operações de provisionamento (usadas por dev:spelt-purchase e provisioning real) ──

    /** Lista os planos do Seller (retorna a lista, não o paginador). */
    public function listPlans(): ?array
    {
        $paginator = $this->get('plan');

        return $paginator === null ? null : ($paginator['data'] ?? []);
    }

    /** Cria um customer (Tenant) sob o Seller. Ver External v1 POST /customer. */
    public function createCustomer(array $data): ?array
    {
        return $this->post('customer', $data);
    }

    /** Cria um usuário de login para o customer (para acessar o portal Customer). */
    public function createCustomerUser(string $customerId, array $data): ?array
    {
        return $this->post("customer/{$customerId}/user", $data);
    }

    /** Cria uma assinatura (opcionalmente com fatura). Ver External v1 POST /subscription. */
    public function createSubscription(array $data): ?array
    {
        return $this->post('subscription', $data);
    }

    /** Detalha uma fatura (para ler o total antes de quitar). */
    public function getInvoice(int|string $id): ?array
    {
        return $this->get("invoice/{$id}");
    }

    /**
     * Quita uma fatura sem dinheiro real (Payment COMPLETED via applyDiscount no Spelt).
     * Com amount = total, dispara syncStatus → activateLinkedEntities (ativa + concede créditos).
     */
    /**
     * Quita a fatura pelo saldo restante.
     *
     * O Spelt deixou de aceitar `amount` neste endpoint em 2026-09-01 — a fatura é sempre
     * quitada pelo saldo restante, e desconto/ajuste parcial passou a ser
     * `POST /invoice/{id}/discount`. Enviar `amount` aqui devolve 400.
     */
    public function payInvoice(int|string $id, ?string $paymentMethodType = null, ?string $reason = null): ?array
    {
        return $this->post("invoice/{$id}/pay", array_filter([
            'payment_method_type' => $paymentMethodType,
            'reason' => $reason,
        ]));
    }

    /** Último erro da External API (status + corpo) para depuração em comandos. */
    public function lastError(): ?array
    {
        return $this->lastError;
    }

    /** Valida um token de SSO (endpoint público, sem auth). Retorna os dados do customer. */
    public function validateSso(string $token): ?array
    {
        $response = Http::acceptJson()->timeout(10)
            ->get($this->url('/api/public/sso/validate'), ['token' => $token]);

        return $response->successful() ? $response->json('data') : null;
    }

    protected function get(string $path): ?array
    {
        $response = $this->http()->get($this->externalUrl($path));

        return $this->unwrap($response, 'GET', $path);
    }

    protected function post(string $path, array $body): ?array
    {
        $response = $this->http()->post($this->externalUrl($path), $body);

        return $this->unwrap($response, 'POST', $path);
    }

    protected function put(string $path, array $body): ?array
    {
        $response = $this->http()->put($this->externalUrl($path), $body);

        return $this->unwrap($response, 'PUT', $path);
    }

    protected function unwrap($response, string $method, string $path): ?array
    {
        if ($response->successful()) {
            $this->lastError = null;

            return $response->json('data');
        }

        $this->lastError = ['status' => $response->status(), 'body' => $response->json() ?? $response->body()];

        Log::warning('[Spelt] External API falhou', [
            'method' => $method, 'path' => $path, 'status' => $response->status(),
        ]);

        return null;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken((string) config('services.spelt.api_key'))
            ->timeout(10);
    }

    protected function externalUrl(string $path): string
    {
        return $this->url('/api/seller/external/v1/'.ltrim($path, '/'));
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.spelt.url'), '/').$path;
    }
}
