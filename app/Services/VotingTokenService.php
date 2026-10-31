<?php

namespace App\Services;

use App\Mail\VotingInvitation;
use App\Models\User;
use App\Models\VotingAuditLog;
use App\Models\VotingInvitationLog;
use App\Models\VotingToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class VotingTokenService
{
    /**
     * @return array{token: string, record: VotingToken}
     */
    public function issue(User $user, CarbonInterface $expiresAt, ?User $actor = null): array
    {
        return DB::transaction(function () use ($user, $expiresAt, $actor): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            VotingToken::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->update(['revoked_at' => now('UTC')]);

            $plainToken = bin2hex(random_bytes(32));
            $record = VotingToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'token_ciphertext' => $plainToken,
                'expires_at' => $expiresAt,
            ]);

            VotingAuditLog::create([
                'actor_id' => $actor?->id,
                'event_type' => 'token_issued',
                'metadata' => ['user_id' => $user->id, 'voting_token_id' => $record->id],
            ]);

            return ['token' => $plainToken, 'record' => $record];
        });
    }

    public function authenticate(string $submittedToken): ?User
    {
        $submittedToken = trim($submittedToken);

        if (preg_match('/\A[a-f0-9]{64}\z/', $submittedToken) !== 1) {
            return null;
        }

        $token = VotingToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $submittedToken))
            ->first();

        if ($token === null || ! $token->isUsable() || ! $token->user->is_active) {
            return null;
        }

        $token->forceFill(['last_used_at' => now('UTC')])->save();

        return $token->user;
    }

    public function issueAndSend(User $recipient, ?User $actor = null): bool
    {
        $expiresAt = now('UTC')->addDays(config('voting.token_lifetime_days'));
        $issued = $this->issue($recipient, $expiresAt, $actor);

        return $this->sendToken($recipient, $issued['record'], $issued['token'], $actor);
    }

    public function sendExistingToken(User $recipient, ?User $actor = null): bool
    {
        $token = VotingToken::query()
            ->where('user_id', $recipient->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->latest('id')
            ->first();

        if ($token === null || $token->token_ciphertext === null) {
            return false;
        }

        return $this->sendToken($recipient, $token, $token->token_ciphertext, $actor);
    }

    private function sendToken(User $recipient, VotingToken $tokenRecord, string $plainToken, ?User $actor): bool
    {
        try {
            Mail::to($recipient)->send(new VotingInvitation(
                user: $recipient,
                token: $plainToken,
                votingUrl: config('voting.shared_url') ?: route('voting.index'),
            ));

            VotingInvitationLog::create([
                'user_id' => $recipient->id,
                'voting_token_id' => $tokenRecord->id,
                'actor_id' => $actor?->id,
                'status' => 'sent',
                'sent_at' => now('UTC'),
            ]);

            return true;
        } catch (\Throwable $exception) {
            VotingInvitationLog::create([
                'user_id' => $recipient->id,
                'voting_token_id' => $tokenRecord->id,
                'actor_id' => $actor?->id,
                'status' => 'failed',
                'failure_reason' => class_basename($exception),
            ]);

            return false;
        }
    }
}
