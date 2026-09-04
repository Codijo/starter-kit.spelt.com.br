<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // billing_subscriptions — espelho do estado de billing + entitlements do Spelt (hasOne).
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('account_id')->constrained('core_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('spelt_subscription_id')->nullable()->index();
            $table->string('plan_id', 26)->nullable();
            $table->string('plan_name')->nullable();
            $table->string('plan_slug')->nullable();
            $table->string('status')->default('inactive');   // inactive | active | past_due | cancelled
            $table->boolean('has_access')->default(false);
            $table->json('entitlements')->nullable();         // snapshot das features do plano (§9)
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
