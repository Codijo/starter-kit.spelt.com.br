<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Drivers\FakeDriver;
use App\Services\Ai\Drivers\OpenRouterDriver;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\DTO\AiUsageContext;
use App\Services\Ai\Exceptions\AiException;

/**
 * Roteia para a implementação de LLM correta a partir do PERFIL (config/ai.php). O código do
 * produto injeta o AiProvider e chama:
 *
 *     $ai->driver('creative')->chat($request);                  // sem log de uso
 *     $ai->chat($request, 'creative', $usageContext);           // com log de uso (ai_usage_logs)
 *
 * O modelo vive no perfil, nunca na chamada. Trocar OpenRouter → Anthropic/OpenAI direto = nova
 * entrada em `ai.drivers` + novo Driver + case aqui; o chamador não muda. Espelha o
 * GatewayResolverService.
 */
final class AiProvider
{
    public function __construct(private readonly AiUsageLogger $logger) {}

    /** Resolve o driver do perfil informado (ou o `default`). Não registra uso. */
    public function driver(?string $profile = null): AiDriver
    {
        [$driverName, $profileConfig] = $this->resolveProfile($profile);

        return $this->makeDriver($driverName, $profileConfig);
    }

    /**
     * Chat no perfil informado (ou default). Se `$usage` for passado, grava uma linha em
     * `ai_usage_logs` com tokens/custo + o contexto de auditoria.
     */
    public function chat(AiRequest $request, ?string $profile = null, ?AiUsageContext $usage = null): AiResponse
    {
        [$driverName, $profileConfig] = $this->resolveProfile($profile);

        $response = $this->makeDriver($driverName, $profileConfig)->chat($request);

        if ($usage !== null) {
            $this->logger->log($response, $driverName, $usage);
        }

        return $response;
    }

    /** @return array{0:string,1:array} [driverName, profileConfig] */
    private function resolveProfile(?string $profile): array
    {
        $profileName = $profile ?: (string) config('ai.default_profile', 'default');
        $profileConfig = config("ai.profiles.{$profileName}");

        if (! is_array($profileConfig)) {
            throw new AiException("Perfil de IA desconhecido: {$profileName}.");
        }

        return [(string) ($profileConfig['driver'] ?? ''), $profileConfig];
    }

    private function makeDriver(string $driverName, array $profileConfig): AiDriver
    {
        $driverConfig = (array) config("ai.drivers.{$driverName}", []);

        return match ($driverName) {
            'openrouter' => new OpenRouterDriver($driverConfig, $profileConfig),
            'fake'       => new FakeDriver($driverConfig, $profileConfig),
            default      => throw new AiException("Driver de IA desconhecido: {$driverName}."),
        };
    }
}
