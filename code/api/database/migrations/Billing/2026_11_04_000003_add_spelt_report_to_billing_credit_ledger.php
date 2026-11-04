<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O ledger vira a fila do consumo que ainda não foi contado ao Spelt (CreditUsageReporter).
        // `spelt_report_reference` é gravada ANTES do envio e reaproveitada no retry: é ela que faz
        // o Spelt tratar a segunda tentativa como replay, e não como débito novo.
        Schema::table('billing_credit_ledger', function (Blueprint $table) {
            $table->string('spelt_report_reference', 64)->nullable()->after('reference');
            $table->timestamp('spelt_reported_at')->nullable()->after('spelt_report_reference');

            $table->index(['account_id', 'type', 'spelt_reported_at'], 'billing_credit_ledger_report_index');
            $table->index('spelt_report_reference');
        });
    }

    public function down(): void
    {
        Schema::table('billing_credit_ledger', function (Blueprint $table) {
            $table->dropIndex('billing_credit_ledger_report_index');
            $table->dropIndex(['spelt_report_reference']);
            $table->dropColumn(['spelt_report_reference', 'spelt_reported_at']);
        });
    }
};
