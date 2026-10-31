<?php

namespace App\Models;

use Database\Factories\VotingInvitationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'voting_token_id', 'actor_id', 'status', 'failure_reason', 'sent_at'])]
class VotingInvitationLog extends Model
{
    /** @use HasFactory<VotingInvitationLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votingToken(): BelongsTo
    {
        return $this->belongsTo(VotingToken::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
