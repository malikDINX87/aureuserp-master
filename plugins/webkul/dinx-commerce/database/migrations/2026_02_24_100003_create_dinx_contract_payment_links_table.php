<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_contract_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('dinx_contracts')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('accounts_account_payments')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'payment_id']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_contract_payment_links');
    }
};
