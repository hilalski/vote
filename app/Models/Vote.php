<?php

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['round_id', 'voter_id', 'candidate_id'])]
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function votingRound(): BelongsTo
    {
        return $this->belongsTo(VotingRound::class, 'round_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
