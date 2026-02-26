<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_paypal_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('accounts_account_moves')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('dinx_contracts')->nullOnDelete();
            $table->string('paypal_order_id')->unique();
            $table->string('paypal_capture_id')->nullable()->index();
            $table->string('status')->default('created')->index();
            $table->decimal('amount', 20, 4)->default(0);
            $table->string('currency', 10);
            $table->text('approval_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_paypal_orders');
    }
};
