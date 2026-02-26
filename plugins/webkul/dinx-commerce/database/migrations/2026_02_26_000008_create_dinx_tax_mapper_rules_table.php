<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_tax_mapper_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('match_pattern');
            $table->foreignId('tax_id')->nullable()->constrained('accounts_taxes')->nullOnDelete();
            $table->decimal('rate_override', 8, 4)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_tax_mapper_rules');
    }
};
