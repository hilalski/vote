<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_active', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function votingTokens(): HasMany
    {
        return $this->hasMany(VotingToken::class);
    }

    public function votesCast(): HasMany
    {
        return $this->hasMany(Vote::class, 'voter_id');
    }

    public function votesReceived(): HasMany
    {
        return $this->hasMany(Vote::class, 'candidate_id');
    }

    public function candidateRounds(): BelongsToMany
    {
        return $this->belongsToMany(VotingRound::class, 'round_candidates', 'user_id', 'round_id');
    }

    public function eligibleRounds(): BelongsToMany
    {
        return $this->belongsToMany(VotingRound::class, 'round_eligible_voters', 'user_id', 'round_id');
    }

    public function hasVoted(int $roundId): bool
    {
        return $this->votesCast()->where('round_id', $roundId)->exists();
    }
}
