<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onde os jobs que falharam ficam registrados.
 *
 * A fila é Redis, então as tabelas `jobs` e `cache` não fazem falta — mas o driver de FALHA
 * é `database` por padrão, e sem esta tabela o Laravel tenta gravar a falha, não consegue, e
 * registra no log um erro sobre a tabela ausente. A falha original some junto.
 *
 * Foi assim que um job morreu sem deixar rastro num produto do kit: ele estourou por
 * configuração, a gravação da falha estourou por tabela ausente, e `queue:failed` respondeu
 * com outro erro ainda. Três erros encobrindo o primeiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
