@php($isAdmin = $isAdmin ?? false)
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>{{ $title ?? 'Pemilihan Ketua HAISSTIS' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap"
        rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/voting.css') }}" rel="stylesheet">
</head>

<body>
    @if ($isAdmin)
        <header id="header" class="header fixed-top d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-between">
                <a class="logo d-flex align-items-center" href="{{ route('admin.voting.dashboard') }}">
                    <span class="brand-mark me-2"><i class="bi bi-check2-square"></i></span>
                    <span class="d-none d-lg-block">Voting</span>
                </a>
            </div>
            <nav class="header-nav ms-auto">
                <ul class="d-flex align-items-center mb-0 list-unstyled">
                    <li class="nav-item dropdown pe-3">
                        <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle fs-4"></i>
                            <span class="d-none d-md-block dropdown-toggle ps-2">{{ auth()->user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                            <li class="dropdown-header">
                                <h6>{{ auth()->user()->name }}</h6><span>Administrator</span>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button class="dropdown-item d-flex align-items-center" type="submit"><i
                                            class="bi bi-box-arrow-right"></i><span>Keluar</span></button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </header>
        <aside id="sidebar" class="sidebar">
            <ul class="sidebar-nav" id="sidebar-nav">
                <li class="nav-item"><a
                        class="nav-link {{ request()->routeIs('admin.voting.dashboard') ? 'active' : 'collapsed' }}"
                        href="{{ route('admin.voting.dashboard') }}"><i
                            class="bi bi-grid"></i><span>Dashboard</span></a></li>
                <li class="nav-heading">Manajemen voting</li>
                <li class="nav-item"><a class="nav-link collapsed" href="{{ route('voting.index') }}"><i
                            class="bi bi-box-arrow-up-right"></i><span>Lihat halaman voter</span></a></li>
            </ul>
        </aside>
        <main id="main" class="main admin-main">
        @else
            <main class="voting-main">
                <div class="voting-shell">
    @endif
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button
                class="btn-close" data-bs-dismiss="alert" type="button"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button
                class="btn-close" data-bs-dismiss="alert" type="button"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Mohon periksa kembali isian yang ditandai.</div>
    @endif

    @yield('content')

    @if ($isAdmin)
        </main>
    @else
        </div>
        </main>
    @endif
    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>

</html>
