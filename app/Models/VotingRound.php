<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\VotingRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['round_number', 'name', 'status', 'starts_at', 'ends_at'])]
class VotingRound extends Model
{
    /** @use HasFactory<VotingRoundFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FINALIZED = 'finalized';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'finalized_at' => 'datetime',
            'winner_resolved_at' => 'datetime',
        ];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'round_id');
    }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'round_candidates', 'round_id', 'user_id')
            ->withPivot('created_at');
    }

    public function eligibleVoters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'round_eligible_voters', 'round_id', 'user_id')
            ->withPivot('created_at');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function isOpenForVoting(CarbonInterface $at): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->starts_at->lessThanOrEqualTo($at)
            && $this->ends_at->greaterThan($at);
    }
}
