<?php

namespace App\Http\Controllers\Core\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contexto do usuário autenticado: conta, assinatura, entitlements e saldo de créditos.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load('account.subscription');
        $account = $user->account;

        return $this->ok([
            'user' => $user->only(['id', 'name', 'email', 'avatar_url']),
            'account' => $account?->only(['id', 'name', 'status']),
            'subscription' => $account?->subscription,
            'entitlements' => $account?->subscription?->entitlements,
            'credit_balance' => $account?->creditBalance(),
            'has_access' => $account?->hasAccess(),
        ]);
    }
}
