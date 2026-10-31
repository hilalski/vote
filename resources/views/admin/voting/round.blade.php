@extends('layouts.voting', ['isAdmin' => true])

@section('content')
    <div class="pagetitle">
        <h1>Putaran {{ $round->round_number }} — {{ $round->name }}</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.voting.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Detail putaran</li>
            </ol>
        </nav>
    </div>
    <section class="section">
        <div class="row">
            <div class="col-lg-8">
                <section class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <h5 class="card-title mb-3">{{ $resultsVisible ? 'Peringkat Kandidat' : 'Kandidat Terdaftar' }}
                            </h5>
                            @if ($resultsVisible)
                                <a class="btn btn-sm btn-outline-primary mb-3"
                                    href="{{ route('admin.voting.rounds.export.rankings', $round) }}"><i
                                        class="bi bi-download me-1"></i>Export CSV</a>
                            @endif
                        </div>
                        @if (!$resultsVisible)
                            <p class="small text-muted">Peringkat dan perolehan suara baru tersedia setelah putaran ditutup.
                            </p>
                        @endif
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        @if ($resultsVisible)
                                            <th>Peringkat</th>
                                        @endif
                                        <th>
                                            Kandidat</th>
                                        @if ($resultsVisible)
                                            <th class="text-end">Suara</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($resultsVisible)
                                        @forelse ($rankings as $index => $candidate)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td><span class="fw-semibold">{{ $candidate->name }}</span><span
                                                        class="d-block small text-muted">{{ $candidate->email }}</span></td>
                                                <td class="text-end fw-bold">{{ $candidate->total_votes }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="text-center text-muted py-4" colspan="3">Daftar kandidat belum
                                                    dikunci.</td>
                                            </tr>
                                        @endforelse
                                    @else
                                        @forelse ($candidates as $candidate)
                                            <tr>
                                                <td><span class="fw-semibold">{{ $candidate->name }}</span><span
                                                        class="d-block small text-muted">{{ $candidate->email }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="text-center text-muted py-4">Daftar kandidat belum dikunci.</td>
                                            </tr>
                                        @endforelse
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="card info-card blue">
                    <div class="card-body">
                        <h5 class="card-title">Partisipasi</h5>
                        <h2 class="mb-1">{{ $turnout['percentage'] }}%</h2>
                        <p class="text-muted mb-0">{{ $turnout['voted'] }} dari {{ $turnout['eligible'] }} pemilih
                            snapshot telah memberikan suara.</p>
                    </div>
                </section>
                <section class="card">
                    <div class="card-body">
                        <h5 class="card-title">Kontrol Putaran</h5>
                        <p class="small text-muted">Status saat ini: <span
                                class="fw-semibold text-uppercase">{{ $round->status }}</span></p>
                        @if ($round->status === 'draft')
                            <form method="POST" action="{{ route('admin.voting.rounds.open', $round) }}">@csrf<button
                                    class="btn btn-primary w-100" type="submit">Buka Putaran</button></form>
                        @elseif ($round->status === 'open')
                            <form method="POST" action="{{ route('admin.voting.rounds.close', $round) }}">@csrf<button
                                    class="btn btn-outline-danger w-100" type="submit">Tutup Putaran</button></form>
                        @elseif ($round->status === 'closed')
                            @if ($round->round_number === 1)
                                <div class="alert alert-info small"><strong>Tetapkan finalis.</strong> Peringkat hanya
                                    menjadi referensi. Pilih tepat tiga kandidat yang akan melaju ke Putaran 2.</div>
                                <form method="POST" action="{{ route('admin.voting.rounds.finalize', $round) }}">@csrf
                                    @foreach ($rankings as $candidate)
                                        <label class="form-check small mb-2"><input class="form-check-input"
                                                name="finalist_ids[]" type="checkbox" value="{{ $candidate->id }}"><span
                                                class="form-check-label">{{ $candidate->name }} <span
                                                    class="text-muted">({{ $candidate->total_votes }}
                                                    suara)</span></span></label>
                                    @endforeach
                                    <label class="form-label small mt-2">Catatan keputusan <span
                                            class="text-muted">(opsional)</span></label>
                                    <textarea class="form-control mb-3" name="explanation" rows="3"></textarea><button class="btn btn-primary w-100" type="submit">Tetapkan
                                        3 finalis dan finalisasi</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.voting.rounds.finalize', $round) }}">
                                    @csrf<button class="btn btn-primary w-100" type="submit">Finalisasi Putaran</button>
                                </form>
                            @endif
                        @elseif (
                            $round->round_number === 2 &&
                                $round->winner_id === null &&
                                $outcome !== null &&
                                $outcome['tied_for_first']->count() > 1)
                            <div class="alert alert-warning small"><strong>TIE / perlu keputusan.</strong> Tidak ada
                                pemenang otomatis.</div>
                            <form method="POST" action="{{ route('admin.voting.rounds.resolve_tie', $round) }}">@csrf
                                <label class="form-label">Pemenang hasil proses eksternal</label><select
                                    class="form-select mb-3" name="winner_id" required>
                                    @foreach ($outcome['tied_for_first'] as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                    @endforeach
                                </select>
                                <label class="form-label">Alasan keputusan</label>
                                <textarea class="form-control mb-3" name="explanation" required rows="3"></textarea><button class="btn btn-outline-primary w-100" type="submit">Catat
                                    resolusi seri</button>
                            </form>
                        @else
                            <div class="status-strip rounded p-3 small">Putaran telah final dan bersifat hanya-baca.</div>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <section class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <h5 class="card-title mb-2">
                        {{ $resultsVisible ? 'Audit Pilihan Pemilih' : 'Audit Partisipasi Pemilih' }}</h5>
                    @if ($resultsVisible)
                        <a class="btn btn-sm btn-outline-primary mb-2"
                            href="{{ route('admin.voting.rounds.export.audit', $round) }}"><i
                                class="bi bi-download me-1"></i>Export CSV</a>
                    @endif
                </div>
                <p class="small text-muted">
                    {{ $resultsVisible ? 'Hanya administrator yang berwenang dapat melihat pasangan pemilih dan kandidat.' : 'Kandidat yang dipilih akan ditampilkan setelah putaran ditutup.' }}
                </p>
                <form class="row g-2 mb-3" method="GET">
                    <div class="col-sm-6"><input class="form-control" name="search"
                            placeholder="{{ $resultsVisible ? 'Cari nama atau email' : 'Cari nama pemilih' }}"
                            value="{{ $search }}"></div>
                    <div class="col-sm-auto"><button class="btn btn-outline-primary" type="submit">Cari</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pemilih</th>
                                @if ($resultsVisible)
                                    <th>Kandidat dipilih</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($votes as $vote)
                                <tr>
                                    <td class="small text-muted">
                                        {{ $vote->created_at->timezone(config('voting.timezone'))->format('d M Y H:i') }}
                                        WIB</td>
                                    <td><span class="fw-semibold">{{ $vote->voter->name }}</span><span
                                            class="d-block small text-muted">{{ $vote->voter->email }}</span></td>
                                    @if ($resultsVisible)
                                        <td>{{ $vote->candidate->name }}</td>
                                    @endif
                                </tr>
                            @empty<tr>
                                    <td class="text-center text-muted py-4" colspan="{{ $resultsVisible ? 3 : 2 }}">Belum
                                        ada suara yang tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $votes->links() }}
            </div>
        </section>

        @if ($showClosedResults)
            <div aria-labelledby="closed-results-title" class="modal fade" id="closed-results-modal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title fs-5" id="closed-results-title">Hasil Putaran
                                    {{ $round->round_number }}</h2>
                                <p class="small text-muted mb-0">Putaran telah ditutup. Berikut hasil akhir dari Putaran 2.
                                </p>
                            </div><button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                        </div>
                        <div class="modal-body">
                            <h3 class="h6 fw-bold mb-3">Peringkat Kandidat</h3>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Peringkat</th>
                                            <th>Kandidat</th>
                                            <th class="text-end">Suara</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rankings as $index => $candidate)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $candidate->name }}</td>
                                                <td class="text-end fw-bold">{{ $candidate->total_votes }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-primary" data-bs-dismiss="modal"
                                type="button">Tutup</button></div>
                    </div>
                </div>
            </div>
            @push('scripts')
                <script>
                    new bootstrap.Modal(document.getElementById('closed-results-modal')).show();
                </script>
            @endpush
        @endif
    </section>
@endsection
