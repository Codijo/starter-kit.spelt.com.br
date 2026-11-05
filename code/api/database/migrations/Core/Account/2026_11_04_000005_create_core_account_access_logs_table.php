<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_account_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('account_id')->constrained('core_accounts')->cascadeOnDelete();

            // Sem FK: o registro sobrevive à remoção do usuário, porque a obrigação é de 7 meses.
            // Por isso a identidade vai copiada: sem o usuário, o id não diria de quem foi o acesso.
            $table->string('user_id', 26);
            $table->string('spelt_user_id', 26)->nullable();
            $table->string('email');

            $table->string('ip', 45);
            $table->unsignedSmallInteger('port')->default(0);   // 0 = desconhecida; NOT NULL para a chave única valer
            $table->string('user_agent', 512)->nullable();

            $table->date('date');                                // dia em UTC
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->unique(['user_id', 'ip', 'port', 'date'], 'core_account_access_logs_key');
            $table->index(['ip', 'date']);
            $table->index('date');                               // o prune
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_account_access_logs');
    }
};
