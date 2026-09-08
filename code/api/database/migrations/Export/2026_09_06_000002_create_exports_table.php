<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O histórico de exportações.
 *
 * Exportar é trabalho assíncrono: o pedido vira uma LINHA, e o arquivo aparece depois. Sem a
 * linha, o cliente clica em "Exportar", nada acontece na tela, e ele clica de novo — três
 * vezes, gerando três varreduras da mesma base.
 *
 * ── Por que o arquivo tem prazo ───────────────────────────────────────────
 *
 * `expires_at` é gravado no pedido (e renovado quando o arquivo fica pronto, para o prazo
 * contar de quando ele passou a existir). Gravar só na conclusão deixaria a exportação que
 * FALHOU sem prazo nenhum — e ela ficaria no histórico para sempre.
 *
 * Um export de leads é uma cópia de dado pessoal de terceiros parada em disco. Prazo curto é
 * higiene, não economia de espaço.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('core_accounts')->cascadeOnDelete();
            // Quem pediu. Fica nulo se o usuário for removido: o histórico da conta continua.
            $table->foreignUlid('user_id')->nullable()->constrained('core_account_users')->nullOnDelete();

            // Chave do ExportCatalog (`lead`, `contact`, `deal`) e formato pedido.
            $table->string('type', 40);
            $table->string('format', 8);

            $table->string('status', 16);
            $table->text('error_message')->nullable();

            // O recorte pedido, como veio da tela. É o que explica por que o arquivo tem 12
            // linhas quando a conta tem 900 — e o que permite repetir a exportação.
            $table->json('filters')->nullable();

            $table->unsignedInteger('row_count')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            // SHA-256: confere se o que se baixou é o que se gerou.
            $table->string('checksum', 64)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // A listagem: as exportações da conta, mais recente primeiro.
            $table->index(['account_id', 'created_at'], 'exports_account_recent_ix');
            // A guarda contra pedido repetido enquanto o anterior não terminou.
            $table->index(['account_id', 'type', 'status'], 'exports_account_type_status_ix');
            // O expurgo varre por prazo, sem olhar a conta.
            $table->index('expires_at', 'exports_expires_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
