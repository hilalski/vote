<?php

namespace App\Models;

use Database\Factories\VotingTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token_hash', 'token_ciphertext', 'expires_at', 'revoked_at', 'last_used_at'])]
#[Hidden(['token_hash', 'token_ciphertext'])]
class VotingToken extends Model
{
    /** @use HasFactory<VotingTokenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'token_ciphertext' => 'encrypted',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
