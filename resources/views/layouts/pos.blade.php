<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Point of Sale · {{ config('pos.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body class="pos-body">
<div class="pos-layout">
    <header class="pos-topbar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">GG</span>
            <span>
                <span class="brand-name">GIT GADGETS</span>
                <span class="brand-sub">Vehicle Parts POS</span>
            </span>
        </a>
        <div class="pos-context">
            <span>Counter</span>
            <b>{{ auth()->user()->name }}</b>
            <span class="pos-status {{ ($boot['shift'] ?? null) ? 'open' : 'closed' }}" id="shift-status">
                {{ ($boot['shift'] ?? null) ? 'Shift open' : 'Shift closed' }}
            </span>
        </div>
        <div class="pos-top-actions">
            <a class="btn btn-secondary" href="{{ route('dashboard') }}">Back office</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Logout</button>
            </form>
            <div class="pos-clock">
                <b data-clock>--:--</b>
                <span data-date>Dubai</span>
            </div>
        </div>
    </header>

    <main class="pos-main">
        @yield('content')
    </main>
</div>
<div id="toast"></div>
<script>
    window.POS_BOOT = @json($boot);
</script>
<script src="{{ asset('js/app.js') }}" defer></script>
<script src="{{ asset('js/pos.js') }}" defer></script>
@stack('scripts')
</body>
</html>
