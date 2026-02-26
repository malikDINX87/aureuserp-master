<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_bank_reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_line_id')->constrained('dinx_bank_statement_lines')->cascadeOnDelete();
            $table->foreignId('move_line_id')->constrained('accounts_account_move_lines')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->string('status')->default('suggested')->index();
            $table->string('reason')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['statement_line_id', 'move_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_bank_reconciliation_matches');
    }
};
