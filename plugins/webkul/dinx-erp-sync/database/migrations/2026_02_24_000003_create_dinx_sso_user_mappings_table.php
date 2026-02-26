<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_sso_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('dinx_user_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('last_role')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_sso_user_mappings');
    }
};
