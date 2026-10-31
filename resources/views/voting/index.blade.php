@extends('layouts.voting')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="brand-mark"><i class="bi bi-check2-square"></i></span>
        <div>
            <h1 class="h3 fw-bold mb-1">Pemilihan Ketua HAISSTIS</h1>
            <p class="text-muted mb-0">Periode 2026 - Kapan</p>
        </div>
        @if ($voter !== null)
            <form class="ms-auto" method="POST" action="{{ route('voting.logout') }}">
                @csrf
                <button class="btn btn-outline-primary btn-sm" type="submit"><i
                        class="bi bi-box-arrow-right me-1"></i>Keluar</button>
            </form>
        @endif
    </div>

    @if ($voter === null)
        <section class="card voting-card">
            <div class="card-body">
                <div class="row align-items-center g-4">
                    <div class="col-lg-6">
                        <span class="badge badge-soft-pink rounded-pill px-3 py-2">Akses pemilih</span>
                        <h2 class="mt-3">Masukkan Token Voting</h2>
                        <p class="text-muted mb-0">Gunakan token pribadi yang dikirimkan melalui email @bps.go.id Anda.</p>
                    </div>
                    <div class="col-lg-6">
                        <form method="POST" action="{{ route('voting.authenticate') }}">
                            @csrf
                            <label class="form-label fw-semibold" for="token">Token pribadi</label>
                            <input autofocus class="form-control form-control-lg @error('token') is-invalid @enderror"
                                id="token" maxlength="64" name="token" pattern="[a-f0-9]{64}" required
                                spellcheck="false" value="{{ old('token') }}">
                            @error('token')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <button class="btn btn-primary w-100 mt-3" type="submit">Masuk<i
                                    class="bi bi-arrow-right ms-2"></i></button>
                        </form>
                        <p class="small text-muted mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Jangan bagikan token
                            kepada siapa pun.</p>
                    </div>
                </div>
            </div>
        </section>
        <p class="text-center text-muted small mt-4 mb-0"><a href="{{ route('admin.login') }}">Masuk sebagai
                administrator</a></p>
    @else
        <section class="card voting-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                    <div>
                        <span class="badge badge-soft-blue rounded-pill px-3 py-2">Halo, {{ $voter->name }}</span>
                        <h2 class="mt-3 mb-1">
                            {{ $round?->round_number === 1 ? 'Putaran 1 — Penjaringan' : ($round?->round_number === 2 ? 'Putaran 2 — Pemilihan Final' : 'Status Voting') }}
                        </h2>
                        <p class="text-muted mb-0">{{ $statusMessage }}</p>
                    </div>
                    @if ($round !== null)
                        <div class="text-lg-end small text-muted">
                            <div>Berakhir</div>
                            <strong>{{ $round->ends_at->timezone(config('voting.timezone'))->translatedFormat('d F Y, H:i') }}
                                WIB</strong>
                        </div>
                    @endif
                </div>

                @if ($vote !== null)
                    <div class="status-strip rounded p-4"><i class="bi bi-check-circle-fill me-2 text-success"></i>Suara
                        Anda berhasil dicatat.</div>
                @elseif ($round !== null && $isEligible)
                    <form id="vote-form" method="POST" action="{{ route('voting.submit') }}">
                        @csrf
                        <fieldset>
                            @if ($round->round_number === 2)
                                <legend class="fs-6 fw-semibold mb-3">Pilih satu kandidat</legend>
                                <div class="vstack gap-2 candidate-radio-list">
                                    @foreach ($candidates as $candidate)
                                        <label class="candidate-option candidate-radio-option">
                                            <span class="d-flex align-items-center gap-3">
                                                <input class="form-check-input mt-0" name="candidate_id" required
                                                    type="radio" value="{{ $candidate->id }}">
                                                <span
                                                    class="candidate-name d-block fw-semibold">{{ $candidate->name }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="candidate-search-wrapper">
                                    <label class="form-label visually-hidden" for="candidate-search">Nama kandidat</label>
                                    <div class="candidate-search input-group input-group-lg">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input autocomplete="off" class="form-control" id="candidate-search"
                                            placeholder="Masukkan nama kandidat..." type="search">
                                    </div>
                                    <div class="candidate-dropdown" id="candidate-dropdown" hidden>
                                        <div class="candidate-results" id="candidate-results">
                                            @foreach ($candidates as $candidate)
                                                <label class="candidate-option"
                                                    data-candidate-name="{{ mb_strtolower($candidate->name) }}" hidden>
                                                    <input class="visually-hidden" name="candidate_id" required
                                                        type="radio" value="{{ $candidate->id }}">
                                                    <span
                                                        class="candidate-name d-block fw-semibold">{{ $candidate->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <p class="candidate-empty-message mb-0" id="candidate-empty-message" hidden>Nama
                                            kandidat tidak ditemukan.</p>
                                    </div>
                                </div>
                                <span aria-live="polite" class="visually-hidden" id="candidate-search-status"></span>
                            @endif
                        </fieldset>
                        <button class="btn btn-primary w-100 mt-4" id="open-confirmation" type="button">Kirim
                            Suara</button>
                    </form>
                @else
                    <div class="status-strip rounded p-4"><i class="bi bi-info-circle-fill me-2"></i>{{ $statusMessage }}
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($voter !== null && $round !== null && $vote === null && $isEligible)
        <div aria-hidden="true" aria-labelledby="confirmation-title" class="modal fade" id="confirmation-modal"
            tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="confirmation-title">Konfirmasi pilihan</h2><button
                            class="btn-close" data-bs-dismiss="modal" type="button"></button>
                    </div>
                    <div class="modal-body">
                        <p>Anda akan memilih:</p>
                        <p class="fw-bold mb-0" id="selected-candidate"></p>
                        <p class="small text-muted mt-3 mb-0">Pilihan tidak dapat diubah setelah dikirim.</p>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal"
                            type="button">Kembali</button><button class="btn btn-primary" id="confirm-submit"
                            type="button">Ya, kirim suara</button></div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        const voteForm = document.getElementById('vote-form');
        const confirmationButton = document.getElementById('open-confirmation');
        const candidateSearch = document.getElementById('candidate-search');
        const candidateDropdown = document.getElementById('candidate-dropdown');
        const candidateEmptyMessage = document.getElementById('candidate-empty-message');
        const candidateSearchStatus = document.getElementById('candidate-search-status');
        const candidateOptions = Array.from(document.querySelectorAll('.candidate-option'));

        if (candidateSearch && candidateDropdown && candidateEmptyMessage && candidateSearchStatus) {
            const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('id-ID');

            const filterCandidates = () => {
                const searchTerm = normalize(candidateSearch.value.trim());
                let resultsCount = 0;

                candidateOptions.forEach((option) => {
                    const matches = searchTerm !== '' && normalize(option.dataset.candidateName).includes(
                        searchTerm);
                    option.hidden = !matches;
                    resultsCount += matches ? 1 : 0;
                });

                candidateDropdown.hidden = searchTerm === '';
                candidateEmptyMessage.hidden = resultsCount !== 0;
                candidateSearchStatus.textContent = searchTerm === '' ?
                    '' :
                    resultsCount === 0 ?
                    'Nama kandidat tidak ditemukan.' :
                    `${resultsCount} kandidat ditemukan.`;
            };

            candidateSearch.addEventListener('input', filterCandidates);
            candidateSearch.addEventListener('focus', filterCandidates);
            candidateSearch.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    candidateDropdown.hidden = true;
                }
            });
            candidateOptions.forEach((option) => {
                option.addEventListener('change', () => {
                    candidateSearch.value = option.querySelector('.candidate-name').textContent.trim();
                    candidateDropdown.hidden = true;
                });
            });
        }

        if (voteForm && confirmationButton) {
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmation-modal'));
            confirmationButton.addEventListener('click', () => {
                const selected = voteForm.querySelector('input[name="candidate_id"]:checked');
                if (!selected) {
                    voteForm.reportValidity();
                    return;
                }
                document.getElementById('selected-candidate').textContent = selected.closest('label').querySelector(
                    '.candidate-name').textContent;
                confirmationModal.show();
            });
            document.getElementById('confirm-submit').addEventListener('click', () => voteForm.submit());
        }
    </script>
@endpush
