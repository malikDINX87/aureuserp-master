<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_expense_category_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('match_pattern');
            $table->foreignId('account_id')->nullable()->constrained('accounts_accounts')->nullOnDelete();
            $table->decimal('confidence_boost', 5, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_expense_category_rules');
    }
};
