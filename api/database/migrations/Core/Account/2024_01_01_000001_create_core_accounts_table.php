<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // core_accounts — identidade da conta (= Spelt customer/Tenant espelhado).
        Schema::create('core_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('spelt_tenant_id', 26)->unique();      // ponte com o Spelt
            $table->string('name');
            $table->string('status')->default('active');           // active | suspended | cancelled
            $table->string('provisioning_status')->default('pending'); // pending | provisioned | suspended | torn_down
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_accounts');
    }
};
