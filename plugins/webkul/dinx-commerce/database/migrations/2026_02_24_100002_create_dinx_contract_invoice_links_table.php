<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_contract_invoice_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('dinx_contracts')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('accounts_account_moves')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'invoice_id']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_contract_invoice_links');
    }
};
