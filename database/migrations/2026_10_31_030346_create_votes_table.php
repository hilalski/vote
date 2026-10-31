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
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('voting_rounds')->restrictOnDelete();
            $table->foreignId('voter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['round_id', 'voter_id']);
            $table->index(['round_id', 'candidate_id']);
            $table->foreign(['round_id', 'voter_id'], 'votes_round_voter_eligible_foreign')
                ->references(['round_id', 'user_id'])
                ->on('round_eligible_voters')
                ->restrictOnDelete();
            $table->foreign(['round_id', 'candidate_id'], 'votes_round_candidate_foreign')
                ->references(['round_id', 'user_id'])
                ->on('round_candidates')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
