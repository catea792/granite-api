<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('revoked_jwt_tokens', function (Blueprint $table) {
            $table->string('jti')->primary();
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('expires_at', 6)->index();
            $table->timestamp('revoked_at', 6);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revoked_jwt_tokens');
    }
};
