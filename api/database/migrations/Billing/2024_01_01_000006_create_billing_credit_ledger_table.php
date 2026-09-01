<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // billing_credit_ledger — ledger append-only de créditos. Saldo = SUM(amount). Ver §8.6.
        Schema::create('billing_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('account_id')->constrained('core_accounts')->cascadeOnDelete();
            $table->string('type');                              // grant | expire | consume
            $table->integer('amount');                           // + concessão · − expiração/consumo
            $table->string('spelt_credit_id')->nullable()->index();
            $table->string('reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // Idempotência do consumo: (account_id, reference) único. Grant/expire têm
            // reference NULL (múltiplos NULLs são permitidos em índice único); um consumo
            // com reference real não duplica em retry de job / refresh de página.
            $table->unique(['account_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_credit_ledger');
    }
};
