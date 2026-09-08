<?php

namespace App\Models\Export;

use App\Enums\Status\Export\ExportStatus;
use App\Models\Core\Account\User;
use App\Services\Export\ExportCatalog;
use App\Traits\Concerns\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Um pedido de exportação e o arquivo que ele produziu.
 *
 * @property ExportStatus $status
 * @property ?array $filters o recorte pedido, como veio da tela
 * @property ?Carbon $expires_at depois disto o arquivo é apagado pelo expurgo
 */
class Export extends Model
{
    use HasUlids;
    use Scope;

    protected $table = 'exports';

    protected $fillable = [
        'account_id', 'user_id', 'type', 'format', 'status', 'filters', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'filters' => 'array',
            'row_count' => 'integer',
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Transições ────────────────────────────────────────────────────────
    //
    // Atribuição direta, e não `update([...])`, de propósito: mass assignment respeita o
    // `$fillable`, e campo esquecido lá é descartado EM SILÊNCIO. Foi assim que, num produto
    // vizinho, toda exportação que falhava perdia a mensagem de erro — o job gravava
    // `error_message`, o fillable não tinha o campo, e ninguém nunca soube por quê.

    public function markProcessing(): void
    {
        $this->status = ExportStatus::PROCESSING;
        $this->started_at = now();
        $this->save();
    }

    /** @param array{name:string,path:string,size:int,mime:string,checksum:string,rows:int} $file */
    public function markCompleted(array $file): void
    {
        $this->status = ExportStatus::COMPLETED;
        $this->file_name = $file['name'];
        $this->file_path = $file['path'];
        $this->file_size = $file['size'];
        $this->mime_type = $file['mime'];
        $this->checksum = $file['checksum'];
        $this->row_count = $file['rows'];
        $this->completed_at = now();
        // O prazo passa a contar de quando o arquivo existe, não de quando foi pedido: fila
        // cheia não pode comer a validade de quem esperou.
        $this->expires_at = now()->addDays(ExportCatalog::ttlDays($this->type));
        $this->save();
    }

    public function markFailed(string $message): void
    {
        $this->status = ExportStatus::FAILED;
        // Cortado no tamanho da coluna de texto? Não — é TEXT. Mas stack trace inteiro na
        // tela do cliente não ajuda ninguém; quem grava aqui já manda a mensagem curta.
        $this->error_message = $message;
        $this->completed_at = now();
        $this->save();
    }

    // ── Consultas ─────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Só o que está pronto, dentro do prazo e com o arquivo realmente em disco.
     *
     * As três condições são independentes: o expurgo pode ter passado, o disco pode ter sido
     * limpo por fora, e a linha continua dizendo "Pronto". Oferecer um botão que devolve 404
     * é pior do que não oferecer botão.
     */
    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::COMPLETED
            && ! $this->isExpired()
            && $this->file_path !== null
            && Storage::disk(ExportCatalog::DISK)->exists($this->file_path);
    }

    public function deleteFile(): void
    {
        if ($this->file_path !== null) {
            Storage::disk(ExportCatalog::DISK)->delete($this->file_path);
        }
    }

    /** @return array<string,mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => ExportCatalog::label($this->type),
            'format' => $this->format,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'open' => $this->status->isOpen(),
            'error_message' => $this->error_message,
            'filters' => $this->filters,
            'row_count' => $this->row_count,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'downloadable' => $this->isDownloadable(),
            'expired' => $this->isExpired(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'requested_by' => $this->relationLoaded('user') ? $this->user?->name : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
