@extends('layouts.voting', ['isAdmin' => true])

@section('content')
    <div class="pagetitle"><h1>Dashboard Voting</h1><nav><ol class="breadcrumb"><li class="breadcrumb-item active">Manajemen dua putaran</li></ol></nav></div>
    <section class="section dashboard">
        <div class="row">
            @foreach ($rounds as $round)
                @php($roundTurnout = $turnout[$round->id])
                <div class="col-xl-6">
                    <article class="card info-card {{ $round->round_number === 2 ? 'blue' : '' }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div class="d-flex align-items-center"><div class="card-icon rounded-circle d-flex align-items-center justify-content-center"><i class="bi {{ $round->round_number === 1 ? 'bi-people' : 'bi-trophy' }}"></i></div><div class="ps-3"><h5 class="card-title mb-1">Putaran {{ $round->round_number }} <span>| {{ $round->name }}</span></h5><h6 class="mb-0">{{ $roundTurnout['voted'] }} / {{ $roundTurnout['eligible'] }}</h6><span class="small text-muted">pemilih telah berpartisipasi ({{ $roundTurnout['percentage'] }}%)</span></div></div>
                                <span class="badge {{ $round->status === 'open' ? 'text-bg-success' : ($round->status === 'finalized' ? 'text-bg-primary' : 'text-bg-secondary') }} text-uppercase">{{ $round->status }}</span>
                            </div>
                            <div class="small text-muted border-top mt-3 pt-3">{{ $round->starts_at->timezone(config('voting.timezone'))->translatedFormat('d M Y H:i') }} — {{ $round->ends_at->timezone(config('voting.timezone'))->translatedFormat('d M Y H:i') }} WIB</div>
                            <a class="btn btn-outline-primary btn-sm mt-3" href="{{ route('admin.voting.rounds.show', $round) }}">Lihat hasil dan kontrol<i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-lg-7">
                <section class="card">
                    <div class="card-body"><h5 class="card-title">Jadwal Putaran</h5>
                        <div class="vstack gap-4">
                            @foreach ($rounds as $round)
                                <form class="row g-3 align-items-end" method="POST" action="{{ route('admin.voting.rounds.schedule', $round) }}">
                                    @csrf
                                    <div class="col-12"><strong>Putaran {{ $round->round_number }} — {{ $round->name }}</strong><span class="ms-2 small text-muted">Hanya saat status draf</span></div>
                                    <div class="col-md-5"><label class="form-label">Mulai (WIB)</label><input class="form-control" {{ $round->status !== 'draft' ? 'disabled' : '' }} name="starts_at" required type="datetime-local" value="{{ $round->starts_at->timezone(config('voting.timezone'))->format('Y-m-d\\TH:i') }}"></div>
                                    <div class="col-md-5"><label class="form-label">Berakhir (WIB)</label><input class="form-control" {{ $round->status !== 'draft' ? 'disabled' : '' }} name="ends_at" required type="datetime-local" value="{{ $round->ends_at->timezone(config('voting.timezone'))->format('Y-m-d\\TH:i') }}"></div>
                                    <div class="col-md-2"><button class="btn btn-outline-primary w-100" {{ $round->status !== 'draft' ? 'disabled' : '' }} type="submit">Simpan</button></div>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-lg-5">
                <section class="card">
                    <div class="card-body"><h5 class="card-title">Kirim Email Token</h5><p class="text-muted small">Pilih pemilih yang sudah memiliki token aktif. Mengirim email tidak membuat atau mengganti token.</p>
                        <form method="POST" action="{{ route('admin.voting.invitations.send') }}">
                            @csrf
                            <label class="form-label visually-hidden" for="invitation-search">Cari pemilih</label>
                            <div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span><input autocomplete="off" class="form-control" id="invitation-search" placeholder="Cari nama atau email pemilih..." type="search"></div>
                            <div class="border rounded p-2 mb-3" style="max-height: 210px; overflow-y: auto;">
                                @foreach ($activeUsers as $user)
                                    <label class="form-check py-1 invitation-user" data-user-search="{{ mb_strtolower($user->name.' '.$user->email) }}"><input class="form-check-input invitation-user-checkbox" name="user_ids[]" type="checkbox" value="{{ $user->id }}"><span class="form-check-label ms-1"><span class="d-block">{{ $user->name }}</span><span class="small text-muted">{{ $user->email }}</span></span></label>
                                @endforeach
                            </div>
                            <label class="form-check mb-2"><input class="form-check-input" id="send-all-invitations" name="send_all" type="checkbox" value="1"><span class="form-check-label"><strong>Kirim undangan ke semua pemilih aktif</strong></span></label>
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-envelope-paper me-1"></i>Kirim email token</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>

        <section class="card"><div class="card-body"><h5 class="card-title">Import Pemilih Aktif</h5><p class="small text-muted">Unggah Excel (.xlsx, .xls, atau .csv) dengan header <code>nama</code> dan <code>email</code>. Pemilih baru ditambahkan, data pemilih lama diperbarui menjadi aktif, dan token yang sudah ada tidak diubah.</p>
            <form class="row g-3 align-items-end" enctype="multipart/form-data" method="POST" action="{{ route('admin.voting.voters.import') }}">@csrf
                <div class="col-md-8"><label class="form-label" for="voters_file">File pemilih</label><input accept=".xlsx,.xls,.csv" class="form-control @error('voters_file') is-invalid @enderror" id="voters_file" name="voters_file" required type="file">@error('voters_file')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-upload me-1"></i>Import pemilih</button></div>
            </form>
        </div></section>

        <section class="card"><div class="card-body"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2"><h5 class="card-title mb-2">Pemilih Aktif &amp; Token</h5><div class="d-flex flex-wrap gap-2 mb-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.voting.tokens.export') }}"><i class="bi bi-download me-1"></i>Export token aktif CSV</a><form method="POST" action="{{ route('admin.voting.tokens.generate_all') }}" onsubmit="return confirm('Buat token baru untuk seluruh pemilih aktif? Setiap token lama akan dicabut. CSV token baru akan langsung diunduh.');">@csrf<input name="confirmed" type="hidden" value="1"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Generate token massal</button></form></div></div><p class="small text-muted">Token tersimpan terenkripsi. Membuka atau mengekspor daftar tidak membuat token baru. Generate massal langsung mengunduh CSV berisi nama, email, dan token baru.</p><div class="row mb-3"><div class="col-md-5"><label class="form-label visually-hidden" for="active-voter-search">Cari pemilih aktif</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input autocomplete="off" class="form-control" id="active-voter-search" placeholder="Cari nama atau email pemilih..." type="search"></div></div></div>
            <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Nama</th><th>Email</th><th>Token terakhir</th><th class="text-end">Aksi</th></tr></thead><tbody>
                @foreach ($activeUsers as $user)
                    @php($token = $user->votingTokens->first())
                    <tr class="active-voter-row" data-user-search="{{ mb_strtolower($user->name.' '.$user->email) }}">
                        <td class="fw-semibold">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($token?->token_ciphertext)
                                <code class="token-value">{{ $token->token_ciphertext }}</code>
                                <div class="small text-muted mt-1">{{ $token->isUsable() ? 'Aktif hingga' : 'Tidak aktif sejak' }} {{ ($token->isUsable() ? $token->expires_at : $token->revoked_at ?? $token->expires_at)->timezone(config('voting.timezone'))->translatedFormat('d M Y H:i') }} WIB</div>
                            @elseif ($token)
                                <span class="small text-muted">Token lama tidak dapat ditampilkan.</span>
                            @else
                                <span class="small text-muted">Belum diterbitkan.</span>
                            @endif
                        </td>
                        <td class="text-end"><form method="POST" action="{{ route('admin.voting.users.token.reissue', $user) }}" onsubmit="return confirm('Buat token baru untuk pemilih ini? Token lama akan dicabut.');">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Regenerate token</button></form></td>
                    </tr>
                @endforeach
            </tbody></table></div>
        </div></section>
    </section>
@endsection

@push('scripts')
    <script>
        const normalizeUserSearch = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('id-ID');
        const bindUserSearch = (inputId, selector) => {
            const input = document.getElementById(inputId);
            const rows = Array.from(document.querySelectorAll(selector));
            if (!input) return;

            input.addEventListener('input', () => {
                const searchTerm = normalizeUserSearch(input.value.trim());
                rows.forEach((row) => {
                    row.hidden = searchTerm !== '' && !normalizeUserSearch(row.dataset.userSearch).includes(searchTerm);
                });
            });
        };

        bindUserSearch('invitation-search', '.invitation-user');
        bindUserSearch('active-voter-search', '.active-voter-row');

        const sendAllInvitations = document.getElementById('send-all-invitations');
        const invitationCheckboxes = Array.from(document.querySelectorAll('.invitation-user-checkbox'));

        if (sendAllInvitations) {
            sendAllInvitations.addEventListener('change', () => {
                invitationCheckboxes.forEach((checkbox) => checkbox.checked = sendAllInvitations.checked);
            });
        }
    </script>
@endpush
