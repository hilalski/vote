<?php

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\VotingAdminController;
use App\Http\Controllers\VotingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/voting');

Route::get('/voting', [VotingController::class, 'index'])->name('voting.index');
Route::post('/voting/auth', [VotingController::class, 'authenticate'])
    ->middleware('throttle:voting-token')
    ->name('voting.authenticate');
Route::post('/voting/submit', [VotingController::class, 'submit'])
    ->middleware(['voter.session', 'throttle:voting-submit'])
    ->name('voting.submit');
Route::post('/voting/logout', [VotingController::class, 'logout'])
    ->middleware('voter.session')
    ->name('voting.logout');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:voting-token')->name('login.store');
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->middleware('voting.admin')->name('logout');

    Route::prefix('voting')->name('voting.')->middleware('voting.admin')->group(function (): void {
        Route::get('/', [VotingAdminController::class, 'index'])->name('dashboard');
        Route::get('/rounds/{round}', [VotingAdminController::class, 'show'])->name('rounds.show');
        Route::get('/rounds/{round}/export/rankings', [VotingAdminController::class, 'exportRankings'])->name('rounds.export.rankings');
        Route::get('/rounds/{round}/export/audit', [VotingAdminController::class, 'exportAudit'])->name('rounds.export.audit');
        Route::post('/rounds/{round}/schedule', [VotingAdminController::class, 'schedule'])->name('rounds.schedule');
        Route::post('/rounds/{round}/open', [VotingAdminController::class, 'open'])->name('rounds.open');
        Route::post('/rounds/{round}/close', [VotingAdminController::class, 'close'])->name('rounds.close');
        Route::post('/rounds/{round}/finalize', [VotingAdminController::class, 'finalize'])->name('rounds.finalize');
        Route::post('/rounds/{round}/resolve-tie', [VotingAdminController::class, 'resolveTie'])->name('rounds.resolve_tie');
        Route::get('/tokens/export', [VotingAdminController::class, 'exportTokens'])->name('tokens.export');
        Route::post('/tokens/generate-all', [VotingAdminController::class, 'generateAllTokens'])->name('tokens.generate_all');
        Route::post('/users/{user}/token/reissue', [VotingAdminController::class, 'reissueToken'])->name('users.token.reissue');
        Route::post('/voters/import', [VotingAdminController::class, 'importActiveVoters'])->name('voters.import');
        Route::post('/invitations/send', [VotingAdminController::class, 'sendInvitations'])->name('invitations.send');
    });
});
