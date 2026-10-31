<?php

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
        Schema::table('voting_tokens', function (Blueprint $table): void {
            $table->text('token_ciphertext')->nullable()->after('token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voting_tokens', function (Blueprint $table): void {
            $table->dropColumn('token_ciphertext');
        });
    }
};
