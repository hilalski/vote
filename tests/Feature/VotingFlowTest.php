<?php

namespace Tests\Feature;

use App\Exceptions\FinalizationBlockedException;
use App\Mail\VotingInvitation;
use App\Models\User;
use App\Models\VotingRound;
use App\Models\VotingToken;
use App\Services\VotingResultsService;
use App\Services\VotingRoundService;
use App\Services\VotingService;
use App\Services\VotingTokenService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class VotingFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_token_authenticates_a_voter_session(): void
    {
        $voter = User::factory()->create();
        $issued = app(VotingTokenService::class)->issue($voter, now('UTC')->addDay());

        $this->post(route('voting.authenticate'), ['token' => $issued['token']])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionHas('voting_voter_id', $voter->id);
    }

    public function test_invalid_expired_and_revoked_tokens_fail_without_creating_a_voter_session(): void
    {
        $voter = User::factory()->create();
        $tokens = app(VotingTokenService::class);
        $first = $tokens->issue($voter, now('UTC')->addDay());
        $expired = $tokens->issue($voter, now('UTC')->subMinute());

        $this->post(route('voting.authenticate'), ['token' => 'not-a-token'])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionMissing('voting_voter_id');
        $this->post(route('voting.authenticate'), ['token' => $first['token']])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionMissing('voting_voter_id');
        $this->post(route('voting.authenticate'), ['token' => $expired['token']])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionMissing('voting_voter_id');
    }

    public function test_round_one_allows_an_eligible_voter_to_vote_for_themselves_once(): void
    {
        $voters = User::factory()->count(4)->create();
        $round = $this->openRoundOne($voters->all());
        $voter = $voters->first();

        $this->withSession(['voting_voter_id' => $voter->id, 'voting_authenticated' => true])
            ->post(route('voting.submit'), ['candidate_id' => $voter->id])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('votes', ['round_id' => $round->id, 'voter_id' => $voter->id, 'candidate_id' => $voter->id]);

        $this->withSession(['voting_voter_id' => $voter->id, 'voting_authenticated' => true])
            ->post(route('voting.submit'), ['candidate_id' => $voters->get(1)->id])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionHas('error', 'Anda sudah memberikan suara pada putaran ini.');

        $this->assertDatabaseCount('votes', 1);
    }

    public function test_voter_ballot_uses_a_name_search_and_does_not_render_candidate_emails(): void
    {
        $voters = User::factory()->count(3)->create();
        $round = $this->openRoundOne($voters->all());
        $voter = $voters->first();
        $candidate = $voters->get(1);

        $this->withSession(['voting_voter_id' => $voter->id, 'voting_authenticated' => true])
            ->get(route('voting.index'))
            ->assertOk()
            ->assertSee('id="candidate-search"', false)
            ->assertSee('id="candidate-dropdown"', false)
            ->assertSee($candidate->name)
            ->assertDontSee($candidate->email);

        $this->assertTrue($round->candidates()->whereKey($candidate->id)->exists());
    }

    public function test_voter_who_skipped_round_one_can_vote_in_round_two(): void
    {
        $voters = User::factory()->count(4)->create();
        [, $roundTwo] = $this->prepareRoundTwo($voters->all());
        app(VotingRoundService::class)->open($roundTwo, $voters->first());
        $skippedVoter = $voters->last();

        $this->withSession(['voting_voter_id' => $skippedVoter->id, 'voting_authenticated' => true])
            ->post(route('voting.submit'), ['candidate_id' => $voters->first()->id])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('votes', ['round_id' => $roundTwo->id, 'voter_id' => $skippedVoter->id]);
    }

    public function test_round_two_ballot_uses_direct_radio_buttons_instead_of_candidate_search(): void
    {
        $voters = User::factory()->count(4)->create();
        [, $roundTwo] = $this->prepareRoundTwo($voters->all());
        app(VotingRoundService::class)->open($roundTwo, $voters->first());

        $this->withSession(['voting_voter_id' => $voters->first()->id, 'voting_authenticated' => true])
            ->get(route('voting.index'))
            ->assertOk()
            ->assertSee('candidate-radio-option', false)
            ->assertDontSee('id="candidate-search"', false)
            ->assertSee($voters->first()->name)
            ->assertSee($voters->get(1)->name)
            ->assertSee($voters->get(2)->name);
    }

    public function test_round_two_rejects_a_candidate_outside_the_frozen_finalists(): void
    {
        $voters = User::factory()->count(4)->create();
        [, $roundTwo] = $this->prepareRoundTwo($voters->all());
        app(VotingRoundService::class)->open($roundTwo, $voters->first());

        $this->withSession(['voting_voter_id' => $voters->first()->id, 'voting_authenticated' => true])
            ->post(route('voting.submit'), ['candidate_id' => $voters->last()->id])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionHas('error', 'Pilihan tidak tersedia untuk putaran voting ini.');

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_vote_is_rejected_when_no_round_is_currently_available(): void
    {
        $voter = User::factory()->create();

        $this->withSession(['voting_voter_id' => $voter->id, 'voting_authenticated' => true])
            ->post(route('voting.submit'), ['candidate_id' => $voter->id])
            ->assertRedirectToRoute('voting.index')
            ->assertSessionHas('error', 'Tidak ada putaran voting yang sedang dibuka.');

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_inactivating_a_voter_blocks_future_voting_without_deleting_a_past_vote(): void
    {
        $voters = User::factory()->count(4)->create();
        $round = $this->openRoundOne($voters->all());
        app(VotingService::class)->cast($voters->first(), $voters->get(1)->id);
        $voters->get(1)->update(['is_active' => false]);

        $this->withSession(['voting_voter_id' => $voters->get(1)->id, 'voting_authenticated' => true])
            ->post(route('voting.submit'), ['candidate_id' => $voters->first()->id])
            ->assertRedirectToRoute('voting.index');

        $this->assertDatabaseHas('votes', ['round_id' => $round->id, 'voter_id' => $voters->first()->id]);
        $this->assertDatabaseCount('votes', 1);
    }

    public function test_rankings_include_zero_vote_candidates(): void
    {
        $voters = User::factory()->count(4)->create();
        $round = $this->openRoundOne($voters->all());
        app(VotingService::class)->cast($voters->first(), $voters->get(1)->id);

        $rankings = app(VotingResultsService::class)->rankings($round);

        $this->assertCount(4, $rankings);
        $this->assertSame(0, $rankings->where('id', $voters->get(2)->id)->first()->total_votes);
        $this->assertSame(0, $rankings->where('id', $voters->get(3)->id)->first()->total_votes);
    }

    public function test_round_one_requires_an_administrator_to_select_exactly_three_finalists(): void
    {
        $voters = User::factory()->count(4)->create();
        $roundOne = $this->openRoundOne($voters->all());
        $voting = app(VotingService::class);
        $voting->cast($voters->get(0), $voters->get(0)->id);
        $voting->cast($voters->get(1), $voters->get(0)->id);
        $voting->cast($voters->get(2), $voters->get(1)->id);
        $voting->cast($voters->get(3), $voters->get(1)->id);
        app(VotingRoundService::class)->close($roundOne, $voters->first());

        $this->expectException(FinalizationBlockedException::class);
        app(VotingRoundService::class)->finalizeRoundOne($roundOne, $voters->first());
    }

    public function test_administrator_can_choose_any_three_round_one_candidates_as_finalists_with_an_audit_log(): void
    {
        $voters = User::factory()->count(4)->create();
        $roundOne = $this->openRoundOne($voters->all());
        $voting = app(VotingService::class);
        $voting->cast($voters->get(0), $voters->get(0)->id);
        $voting->cast($voters->get(1), $voters->get(0)->id);
        $voting->cast($voters->get(2), $voters->get(1)->id);
        $voting->cast($voters->get(3), $voters->get(1)->id);
        $rounds = app(VotingRoundService::class);
        $rounds->close($roundOne, $voters->first());
        VotingRound::factory()->create([
            'round_number' => 2,
            'name' => 'Final',
            'starts_at' => now('UTC')->addDays(2),
            'ends_at' => now('UTC')->addDays(3),
        ]);
        $selectedFinalists = [$voters->get(0)->id, $voters->get(2)->id, $voters->get(3)->id];
        $rounds->finalizeRoundOne($roundOne, $voters->first(), $selectedFinalists, 'Seleksi panitia untuk Putaran 2.');

        $roundTwo = VotingRound::query()->where('round_number', 2)->firstOrFail();

        $this->assertSame(VotingRound::STATUS_FINALIZED, $roundOne->fresh()->status);
        $this->assertDatabaseCount('round_candidates', 7);
        $this->assertSame(3, DB::table('round_candidates')->where('round_id', $roundTwo->id)->count());
        $this->assertEqualsCanonicalizing(
            $selectedFinalists,
            DB::table('round_candidates')->where('round_id', $roundTwo->id)->pluck('user_id')->all(),
        );
        $this->assertDatabaseHas('voting_audit_logs', ['round_id' => $roundOne->id, 'event_type' => 'round_one_finalized_admin_selection']);
    }

    public function test_round_one_rejects_a_finalist_who_was_not_a_candidate_in_its_snapshot(): void
    {
        $voters = User::factory()->count(3)->create();
        $roundOne = $this->openRoundOne($voters->all());
        app(VotingRoundService::class)->close($roundOne, $voters->first());
        VotingRound::factory()->create([
            'round_number' => 2,
            'name' => 'Final',
            'starts_at' => now('UTC')->addDays(2),
            'ends_at' => now('UTC')->addDays(3),
        ]);
        $outsider = User::factory()->create();

        $this->expectException(FinalizationBlockedException::class);
        app(VotingRoundService::class)->finalizeRoundOne(
            $roundOne,
            $voters->first(),
            [$voters->first()->id, $voters->get(1)->id, $outsider->id],
        );
    }

    public function test_authorized_admin_can_export_rankings_and_voter_audit_as_csv(): void
    {
        $voters = User::factory()->count(3)->create();
        $round = $this->openRoundOne($voters->all());
        app(VotingService::class)->cast($voters->first(), $voters->get(1)->id);
        $admin = User::factory()->admin()->create();
        app(VotingRoundService::class)->close($round, $admin);

        $rankings = $this->actingAs($admin)
            ->get(route('admin.voting.rounds.export.rankings', $round))
            ->assertOk()
            ->assertDownload('peringkat-putaran-1.csv');
        $this->assertStringContainsString('Peringkat,Kandidat,"Jumlah Suara"', $rankings->streamedContent());
        $this->assertStringContainsString($voters->get(1)->name, $rankings->streamedContent());

        $audit = $this->get(route('admin.voting.rounds.export.audit', $round))
            ->assertOk()
            ->assertDownload('audit-pilihan-putaran-1.csv');
        $this->assertStringContainsString('"Waktu (WIB)",Pemilih,"Kandidat Dipilih"', $audit->streamedContent());
        $this->assertStringContainsString($voters->first()->name, $audit->streamedContent());
        $this->assertStringContainsString($voters->get(1)->name, $audit->streamedContent());
    }

    public function test_open_round_hides_live_results_and_reveals_them_in_a_modal_after_closing(): void
    {
        $voters = User::factory()->count(3)->create();
        $round = $this->openRoundOne($voters->all());
        app(VotingService::class)->cast($voters->first(), $voters->get(1)->id);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.voting.rounds.show', $round))
            ->assertOk()
            ->assertSee('Kandidat Terdaftar')
            ->assertSee('Audit Partisipasi Pemilih')
            ->assertDontSee('Kandidat dipilih')
            ->assertDontSee('Export CSV');
        $this->get(route('admin.voting.rounds.export.rankings', $round))->assertForbidden();
        $this->get(route('admin.voting.rounds.export.audit', $round))->assertForbidden();

        $this->post(route('admin.voting.rounds.close', $round))
            ->assertRedirectToRoute('admin.voting.rounds.show', $round)
            ->assertSessionHas('show_closed_results', true);
        $this->get(route('admin.voting.rounds.show', $round))
            ->assertOk()
            ->assertSee('id="closed-results-modal"', false)
            ->assertSee('Export CSV');
    }

    public function test_round_two_first_place_tie_does_not_create_a_winner(): void
    {
        $voters = User::factory()->count(4)->create();
        [, $roundTwo] = $this->prepareRoundTwo($voters->all());
        $rounds = app(VotingRoundService::class);
        $rounds->open($roundTwo, $voters->first());
        $voting = app(VotingService::class);
        $voting->cast($voters->get(0), $voters->get(0)->id);
        $voting->cast($voters->get(1), $voters->get(1)->id);
        $voting->cast($voters->get(2), $voters->get(0)->id);
        $voting->cast($voters->get(3), $voters->get(1)->id);
        $rounds->close($roundTwo, $voters->first());
        $rounds->finalizeRoundTwo($roundTwo, $voters->first());

        $this->assertSame(VotingRound::STATUS_FINALIZED, $roundTwo->fresh()->status);
        $this->assertNull($roundTwo->fresh()->winner_id);
        $this->assertDatabaseHas('voting_audit_logs', ['round_id' => $roundTwo->id, 'event_type' => 'round_two_finalized_tie']);
    }

    public function test_token_reissue_revokes_the_previous_token_and_mail_uses_the_shared_url(): void
    {
        Mail::fake();
        $voter = User::factory()->create();
        $tokens = app(VotingTokenService::class);
        $first = $tokens->issue($voter, now('UTC')->addDay());

        $this->assertSame($voter->id, $tokens->authenticate($first['token'])?->id);
        $this->assertTrue($tokens->issueAndSend($voter));

        $this->assertNull($tokens->authenticate($first['token']));
        $this->assertSame(1, VotingToken::query()->where('user_id', $voter->id)->whereNull('revoked_at')->count());
        Mail::assertSent(VotingInvitation::class, function (VotingInvitation $mail) use ($voter): bool {
            return $mail->hasTo($voter->email) && $mail->votingUrl === route('voting.index') && ! str_contains($mail->votingUrl, $mail->token);
        });
    }

    public function test_an_admin_can_view_an_encrypted_at_rest_current_voting_token(): void
    {
        $voter = User::factory()->create();
        $issued = app(VotingTokenService::class)->issue($voter, now('UTC')->addDay());
        $admin = User::factory()->admin()->create();

        $this->assertDatabaseMissing('voting_tokens', ['token_ciphertext' => $issued['token']]);

        $this->actingAs($admin)
            ->get(route('admin.voting.dashboard'))
            ->assertOk()
            ->assertSee($issued['token']);
    }

    public function test_admin_can_send_invitations_to_all_active_non_admin_voters(): void
    {
        Mail::fake();
        $voters = User::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();
        $tokens = app(VotingTokenService::class);
        $tokens->issue($voters->first(), now('UTC')->addDay());
        $tokens->issue($voters->last(), now('UTC')->addDay());

        $this->actingAs($admin)
            ->post(route('admin.voting.invitations.send'), ['send_all' => true])
            ->assertRedirect()
            ->assertSessionHas('success', 'Email token dikirim ke 2 dari 2 pemilih aktif.');

        $this->assertSame(2, VotingToken::query()->count());
        $this->assertDatabaseMissing('voting_tokens', ['user_id' => $admin->id]);
        Mail::assertSent(VotingInvitation::class, 2);
        Mail::assertSent(VotingInvitation::class, fn (VotingInvitation $mail): bool => $mail->hasTo($voters->first()->email));
    }

    public function test_admin_can_generate_all_tokens_and_download_them_as_csv_without_sending_email(): void
    {
        Mail::fake();
        $voters = User::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();
        $previous = app(VotingTokenService::class)->issue($voters->first(), now('UTC')->addDay());

        $response = $this->actingAs($admin)
            ->post(route('admin.voting.tokens.generate_all'), ['confirmed' => true])
            ->assertOk()
            ->assertDownload('token-baru-semua-pemilih.csv');

        $this->assertNull(app(VotingTokenService::class)->authenticate($previous['token']));
        $this->assertSame(2, VotingToken::query()->whereNull('revoked_at')->count());
        $this->assertStringContainsString('Nama,Email,Token', $response->streamedContent());
        $this->assertStringContainsString($voters->first()->email, $response->streamedContent());
        Mail::assertNothingSent();
    }

    public function test_individual_token_regeneration_does_not_send_email(): void
    {
        Mail::fake();
        $voter = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $previous = app(VotingTokenService::class)->issue($voter, now('UTC')->addDay());

        $this->actingAs($admin)
            ->post(route('admin.voting.users.token.reissue', $voter))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(app(VotingTokenService::class)->authenticate($previous['token']));
        $this->assertSame(1, VotingToken::query()->where('user_id', $voter->id)->whereNull('revoked_at')->count());
        Mail::assertNothingSent();
    }

    public function test_opening_or_exporting_the_token_list_does_not_create_new_tokens(): void
    {
        $voter = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $issued = app(VotingTokenService::class)->issue($voter, now('UTC')->addDay());
        $tokenCount = VotingToken::query()->count();

        $this->actingAs($admin)->get(route('admin.voting.dashboard'))->assertOk();
        $response = $this->get(route('admin.voting.tokens.export'))
            ->assertOk()
            ->assertDownload('token-aktif-pemilih.csv');

        $this->assertSame($tokenCount, VotingToken::query()->count());
        $this->assertStringContainsString('Nama,Email,Token', $response->streamedContent());
        $this->assertStringContainsString($voter->email, $response->streamedContent());
        $this->assertStringContainsString($issued['token'], $response->streamedContent());
    }

    public function test_admin_can_import_active_voters_from_excel_without_changing_existing_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $existingVoter = User::factory()->inactive()->create(['email' => 'existing@example.test']);
        $otherVoter = User::factory()->create();
        $tokens = app(VotingTokenService::class);
        $existingToken = $tokens->issue($existingVoter, now('UTC')->addDay());
        $otherToken = $tokens->issue($otherVoter, now('UTC')->addDay());
        $tokenCount = VotingToken::query()->count();

        $file = $this->voterImportFile([
            ['nama', 'email'],
            ['Nama Pemilih Diperbarui', $existingVoter->email],
            ['Pemilih Baru', 'baru@example.test'],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.voting.voters.import'), ['voters_file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', 'Import selesai: 1 pemilih baru dan 1 pemilih diperbarui. Token yang sudah ada tidak diubah.');

        $this->assertDatabaseHas('users', ['email' => $existingVoter->email, 'name' => 'Nama Pemilih Diperbarui', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'baru@example.test', 'name' => 'Pemilih Baru', 'is_active' => true, 'is_admin' => false]);
        $this->assertSame($tokenCount, VotingToken::query()->count());
        $this->assertSame($existingVoter->id, $tokens->authenticate($existingToken['token'])?->id);
        $this->assertSame($otherVoter->id, $tokens->authenticate($otherToken['token'])?->id);
        $this->assertDatabaseMissing('voting_tokens', ['user_id' => User::query()->where('email', 'baru@example.test')->value('id')]);
    }

    public function test_admin_dashboard_rejects_guests_and_allows_an_authorized_admin(): void
    {
        $this->get(route('admin.voting.dashboard'))->assertRedirectToRoute('admin.login');

        $admin = User::factory()->admin()->create();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirectToRoute('admin.voting.dashboard');
    }

    public function test_open_round_one_freezes_new_users_out_of_the_electorate_and_candidate_list(): void
    {
        $voters = User::factory()->count(4)->create();
        $round = $this->openRoundOne($voters->all());
        $newUser = User::factory()->create();

        $this->assertFalse($round->eligibleVoters()->whereKey($newUser->id)->exists());
        $this->assertFalse($round->candidates()->whereKey($newUser->id)->exists());
    }

    public function test_an_administrator_is_excluded_from_the_round_one_electorate_and_candidate_snapshot(): void
    {
        $voters = User::factory()->count(3)->create();
        $administrator = User::factory()->admin()->create();
        $round = VotingRound::factory()->create([
            'round_number' => 1,
            'name' => 'Penjaringan',
            'starts_at' => now('UTC')->subHour(),
            'ends_at' => now('UTC')->addDay(),
        ]);

        app(VotingRoundService::class)->open($round, $administrator);

        $this->assertFalse($round->eligibleVoters()->whereKey($administrator->id)->exists());
        $this->assertFalse($round->candidates()->whereKey($administrator->id)->exists());
        $this->assertCount(3, $round->fresh()->eligibleVoters);
        $this->assertCount(3, $round->fresh()->candidates);
    }

    /**
     * @param  array<int, User>  $voters
     */
    private function openRoundOne(array $voters): VotingRound
    {
        $round = VotingRound::factory()->create([
            'round_number' => 1,
            'name' => 'Penjaringan',
            'starts_at' => now('UTC')->subHour(),
            'ends_at' => now('UTC')->addDay(),
        ]);

        app(VotingRoundService::class)->open($round, $voters[0]);

        return $round->fresh();
    }

    /**
     * @param  array<int, User>  $voters
     * @return array{0: VotingRound, 1: VotingRound}
     */
    private function prepareRoundTwo(array $voters): array
    {
        $roundOne = VotingRound::factory()->create([
            'round_number' => 1,
            'name' => 'Penjaringan',
            'status' => VotingRound::STATUS_FINALIZED,
            'starts_at' => now('UTC')->subDays(5),
            'ends_at' => now('UTC')->subDays(2),
            'finalized_at' => now('UTC')->subDay(),
            'finalized_by' => $voters[0]->id,
        ]);
        $roundTwo = VotingRound::factory()->create([
            'round_number' => 2,
            'name' => 'Final',
            'starts_at' => now('UTC')->subHour(),
            'ends_at' => now('UTC')->addDay(),
        ]);
        $timestamp = now('UTC');
        DB::table('round_eligible_voters')->insert(collect($voters)->map(fn (User $voter): array => [
            'round_id' => $roundTwo->id,
            'user_id' => $voter->id,
            'created_at' => $timestamp,
        ])->all());
        DB::table('round_candidates')->insert(collect($voters)->take(3)->map(fn (User $voter): array => [
            'round_id' => $roundTwo->id,
            'user_id' => $voter->id,
            'created_at' => $timestamp,
        ])->all());

        return [$roundOne, $roundTwo];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function voterImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'voters-import-');
        (new Xlsx($spreadsheet))->save($path);
        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('pemilih.xlsx', $contents);
    }
}
