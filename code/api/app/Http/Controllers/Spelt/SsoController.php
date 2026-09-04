<?php

namespace App\Http\Controllers\Spelt;

use App\Http\Controllers\Controller;
use App\Models\Core\Account\Account;
use App\Services\Core\Account\AccountService;
use App\Services\Spelt\SpeltClient;
use App\Services\Spelt\SubscriptionSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Troca de token SSO → sessão local. O Platform recebe `/auth/spelt?spelt_token=...`
 * (do portal Customer do Spelt) e chama este endpoint; a API valida o token no Spelt,
 * resolve conta+usuário e devolve um token Sanctum do produto. Ver spec §8.1.
 */
class SsoController extends Controller
{
    public function __construct(
        private readonly SpeltClient $spelt,
        private readonly AccountService $accounts,
        private readonly SubscriptionSync $subscriptions,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->input('token') ?? $request->input('spelt_token');

        if (! $token) {
            return $this->fail('Token SSO ausente.', 422);
        }

        $customer = $this->spelt->validateSso($token);

        if (! $customer) {
            return $this->fail('Token SSO inválido ou expirado.', 401);
        }

        [$account, $user] = $this->accounts->upsertFromSso($customer);

        // Baseline da assinatura a partir do payload (webhooks refinam depois).
        $this->syncSubscription($account, $customer['subscriptions'] ?? []);

        $user->forceFill(['last_login_at' => now()])->save();
        $plainToken = $user->createToken('spelt-sso')->plainTextToken;

        return $this->ok([
            'token' => $plainToken,
            'user' => $user->only(['id', 'name', 'email', 'avatar_url']),
            'account' => $account->only(['id', 'name', 'status']),
            'has_access' => $account->fresh('subscription')->hasAccess(),
        ], 201);
    }

    /** Baseline da assinatura no login: escolhe a ativa/trial (ou a 1ª) e aplica via SubscriptionSync. */
    private function syncSubscription(Account $account, array $subscriptions): void
    {
        $chosen = collect($subscriptions)
            ->first(fn ($s) => in_array($s['status'] ?? null, ['active', 'trial'], true))
            ?? ($subscriptions[0] ?? null);

        if ($chosen) {
            $this->subscriptions->apply($account, $chosen);
        }
    }
}
