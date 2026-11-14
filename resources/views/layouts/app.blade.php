<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('pos.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/operations.css') }}?v={{ filemtime(public_path('css/operations.css')) }}">
    @stack('head')
</head>
<body class="@yield('body-class')">
@php
    $user = auth()->user();
    $navItems = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'active' => 'dashboard', 'roles' => ['owner','manager','cashier','storekeeper','accountant'], 'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
        ['route' => 'pos.index', 'label' => 'Point of Sale', 'active' => 'pos.*', 'roles' => ['owner','manager','cashier'], 'icon' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 8H6"/>'],
        ['route' => 'products.index', 'label' => 'Catalogue', 'active' => 'products.*', 'roles' => ['owner','manager','storekeeper'], 'icon' => '<path d="M21 8.4 12 3 3 8.4v7.2L12 21l9-5.4V8.4Z"/><path d="m3 8.4 9 5.4 9-5.4M12 13.8V21"/>'],
        ['route' => 'customers.index', 'label' => 'Customers', 'active' => 'customers.*', 'roles' => ['owner','manager','cashier'], 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ['route' => 'inventory.index', 'label' => 'Inventory', 'active' => 'inventory.*', 'roles' => ['owner','manager','storekeeper'], 'icon' => '<path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z"/><path d="M3 7l9 5 9-5M12 12v10"/>'],
        ['route' => 'sales.index', 'label' => 'Sales', 'active' => 'sales.*', 'roles' => ['owner','manager','cashier'], 'icon' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h3"/>'],
        ['route' => 'reports.index', 'label' => 'Reports', 'active' => 'reports.*', 'roles' => ['owner','manager','accountant'], 'icon' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
        ['route' => 'audit.index', 'label' => 'Audit Trail', 'active' => 'audit.*', 'roles' => ['owner','manager','accountant'], 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>'],
        ['route' => 'users.index', 'label' => 'Users', 'active' => 'users.*', 'roles' => ['owner'], 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ['route' => 'roles.index', 'label' => 'Roles', 'active' => 'roles.*', 'roles' => ['owner'], 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>'],
        ['route' => 'settings.edit', 'label' => 'Settings', 'active' => 'settings.*', 'roles' => ['owner'], 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21h-4v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3v-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V3h4v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1v4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>'],
    ];
@endphp
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route($user->homeRoute()) }}">
            <span class="brand-mark">GG</span>
            <span>
                <span class="brand-name">GIT GADGETS</span>
                <span class="brand-sub">Vehicle Parts POS</span>
            </span>
        </a>

        <div class="nav-label">Workspace</div>
        @foreach($navItems as $item)
            @if($user && $user->canAccessRoute($item['route']))
                <a class="nav-link {{ request()->routeIs($item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach

        <div class="nav-label">Counter</div>
        @if($user && $user->canSell())
            <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v12H4z"/><path d="M8 20h8M12 16v4"/></svg>
                <span>Open POS</span>
            </a>
        @endif
        @if($user->canAccessRoute('sales.index'))<a class="nav-link" href="{{ route('sales.index') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h5"/></svg>
            <span>Today's invoices</span>
        </a>@endif

        <div class="sidebar-footer">
            <div class="user-chip">
                <span class="avatar">{{ strtoupper(substr($user->name ?? 'GG', 0, 2)) }}</span>
                <span>
                    <span class="user-name">{{ $user->name ?? 'Guest' }}</span>
                    <span class="user-role">{{ $user->roleLabel() ?? '' }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}" style="margin-left:auto">
                    @csrf
                    <button class="logout-link" type="submit" style="border:0;background:none;cursor:pointer">Logout</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <div class="topbar-title">@yield('title', 'Dashboard')</div>
                <div class="topbar-sub">@yield('subtitle', 'Git Gadgets vehicle parts operations')</div>
            </div>
            <div class="topbar-right">
                <div class="clock">
                    <div class="clock-time" data-clock>--:--</div>
                    <div class="clock-date" data-date>Dubai</div>
                </div>
                @if($user && $user->canSell())
                    <a class="btn btn-dark btn-sm" href="{{ route('pos.index') }}">Open POS</a>
                @endif
            </div>
        </header>

        <main class="content">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">
                    <strong>Please check the form.</strong>
                    <ul>
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
<script src="{{ asset('js/page-shortcuts.js') }}?v={{ filemtime(public_path('js/page-shortcuts.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
