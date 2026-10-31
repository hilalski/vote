@extends('layouts.voting')

@section('content')
    <section class="card voting-card mx-auto" style="max-width: 460px;">
        <div class="card-body">
            <div class="text-center mb-4"><span class="brand-mark"><i class="bi bi-shield-lock"></i></span><h1 class="h3 mt-3">Masuk Administrator</h1><p class="text-muted mb-0">Kelola jadwal, undangan, hasil, dan audit voting.</p></div>
            <form method="POST" action="{{ route('admin.login.store') }}">
                @csrf
                <div class="mb-3"><label class="form-label" for="email">Email</label><input autocomplete="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" required type="email" value="{{ old('email') }}">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="mb-4"><label class="form-label" for="password">Kata sandi</label><input autocomplete="current-password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required type="password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <button class="btn btn-primary w-100" type="submit">Masuk</button>
            </form>
            <p class="small text-center text-muted mt-4 mb-0"><a href="{{ route('voting.index') }}">Kembali ke voting</a></p>
        </div>
    </section>
@endsection
