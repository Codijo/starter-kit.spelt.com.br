<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal Customer do Seller, aprendido do payload do SSO (`panel_url`). Dispensa a env
 * SPELT_CUSTOMER_PORTAL_URL: o `managePortal()` passa a usar este valor por conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_accounts', function (Blueprint $table) {
            $table->string('spelt_panel_url', 2048)->nullable()->after('spelt_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('core_accounts', function (Blueprint $table) {
            $table->dropColumn('spelt_panel_url');
        });
    }
};
