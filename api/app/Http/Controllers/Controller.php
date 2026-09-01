<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Controller base. Helpers de resposta seguindo o padrão JSON do Spelt
 * (docs/technical/starter-kit.md §16 do repo do Spelt — herdado).
 */
abstract class Controller
{
    /** Item/coleção sob `data`. */
    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /** Criação — 201 com `data`. */
    protected function created(mixed $data): JsonResponse
    {
        return $this->ok($data, 201);
    }

    /** Regra de negócio violada — 400 com `message` (compatível com o modal do Front). */
    protected function fail(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /** Recurso não encontrado — 404 com `error` (convenção do Spelt). */
    protected function notFound(string $error = 'Recurso não encontrado'): JsonResponse
    {
        return response()->json(['error' => $error], 404);
    }
}
