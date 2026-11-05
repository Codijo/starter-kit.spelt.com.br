<?php

namespace App\Models\Core\Account;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AccessLog
 *
 * Registro de acesso à aplicação (Marco Civil, art. 15): data, hora e IP de uso da API autenticada.
 * Uma linha por usuário, IP, porta e dia, com o primeiro e o último uso. Sem a porta (0), é uma linha
 * por dia; com ela, uma por conexão, que é o que um pedido sobre IP compartilhado (CGNAT) pergunta.
 *
 * O login não está aqui: ele acontece no Spelt, que é quem o registra.
 *
 * Sigilo: nenhuma rota expõe a tabela. A consulta é pelo comando `account:access-log`, para atender a
 * ordem judicial.
 *
 * Retenção: 7 meses (os 6 da lei e uma margem), pelo `model:prune` em routes/console.php. Produto que
 * elimina os dados de conta cancelada deixa esta tabela de fora: é obrigação legal, e por isso a
 * identidade do usuário vai copiada na linha (sem FK para o usuário).
 *
 * Regras de negócio ficam nos Services:
 * - App\Services\Core\Account\AccessLogService — grava e resolve o IP real atrás do Cloudflare
 *
 * @property int $id
 * @property string $account_id
 * @property string $user_id
 * @property string|null $spelt_user_id
 * @property string $email
 * @property string $ip
 * @property int $port
 * @property string|null $user_agent
 * @property Carbon $date
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
class AccessLog extends Model
{
    use MassPrunable;

    /** Meses que o registro fica guardado. A Privacidade diz "no mínimo 6 meses". */
    public const RETENTION_MONTHS = 7;

    public $timestamps = false;

    protected $table = 'core_account_access_logs';

    protected $fillable = [
        // === Quem (copiado: sobrevive à remoção do usuário) ===
        'account_id',
        'user_id',
        'spelt_user_id',
        'email',

        // === De onde ===
        'ip',
        'port',             // porta lógica de origem; 0 = desconhecida
        'user_agent',

        // === Quando (UTC) ===
        'date',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** O que o `model:prune` apaga: o dia que passou do prazo. */
    public function prunable(): Builder
    {
        return static::query()->where('date', '<', now()->subMonths(self::RETENTION_MONTHS)->toDateString());
    }
}
