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
        Schema::create('voting_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('round_number')->unique();
            $table->string('name');
            $table->string('status')->default('draft')->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('winner_resolved_at')->nullable();
            $table->foreignId('winner_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('winner_resolution')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voting_rounds');
    }
};
