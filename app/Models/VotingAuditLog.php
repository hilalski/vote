<?php

namespace App\Models;

use Database\Factories\VotingAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'round_id', 'event_type', 'metadata'])]
class VotingAuditLog extends Model
{
    /** @use HasFactory<VotingAuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function votingRound(): BelongsTo
    {
        return $this->belongsTo(VotingRound::class, 'round_id');
    }
}
