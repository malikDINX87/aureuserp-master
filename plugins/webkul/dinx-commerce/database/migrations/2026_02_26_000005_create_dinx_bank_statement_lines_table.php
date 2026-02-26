<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('dinx_bank_statement_imports')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->date('transaction_date')->nullable();
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('amount', 20, 4);
            $table->decimal('balance', 20, 4)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->foreignId('suggested_account_id')->nullable()->constrained('accounts_accounts')->nullOnDelete();
            $table->foreignId('matched_move_line_id')->nullable()->constrained('accounts_account_move_lines')->nullOnDelete();
            $table->string('status')->default('unmatched')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['import_id', 'line_number']);
            $table->index(['import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_bank_statement_lines');
    }
};
