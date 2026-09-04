<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->ulid('account_id')->nullable()->index();
            $table->ulid('user_id')->nullable()->index();

            $table->string('driver');
            $table->string('provider');
            $table->string('model');
            $table->string('action')->index();

            $table->string('context_type')->nullable();
            $table->string('context_id')->nullable();

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            $table->decimal('cost_input_usd', 16, 8)->nullable();
            $table->decimal('cost_output_usd', 16, 8)->nullable();
            $table->decimal('total_cost_usd', 16, 8)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
