<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('pos.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-body">
    <section class="login-brand">
        <div class="brand">
            <span class="brand-mark">GG</span>
            <span>
                <span class="brand-name">GIT GADGETS</span>
                <span class="brand-sub">Vehicle Parts POS</span>
            </span>
        </div>
        <h1 class="login-headline">Counter speed.<br><span>Parts accuracy.</span></h1>
        <p class="login-copy">A fitment-first point of sale built for the way a Dubai spare-parts counter actually works — search by vehicle, part number, or cross-reference, and keep the queue moving.</p>
        <div class="login-features">
            <div class="login-feature"><span class="tick">✓</span><span><b>Fitment-first search.</b> Find the part by car, model, year, or any reference number.</span></div>
            <div class="login-feature"><span class="tick">✓</span><span><b>Secret cost codes.</b> The counter sees the code; the customer never sees the cost.</span></div>
            <div class="login-feature"><span class="tick">✓</span><span><b>UAE VAT ready.</b> 5% VAT, sequential invoices, split payments and credit notes.</span></div>
            <div class="login-feature"><span class="tick">✓</span><span><b>Stock you can trust.</b> Every sale, receipt, return and adjustment is in the ledger.</span></div>
        </div>
    </section>

    <section class="login-side">
        <div class="login-card">
            <h1>Welcome back</h1>
            <p>Sign in to open the counter or the back office.</p>

            @if($errors->any())
                <div class="alert alert-error">
                    @foreach($errors->all() as $error){{ $error }}@if(!$loop->last)<br>@endif @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="field mb-2">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', 'cashier@gitgadgets.test') }}" required autofocus autocomplete="username">
                </div>
                <div class="field mb-2">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" value="password" required autocomplete="current-password">
                </div>
                <label class="check mb-2"><input type="checkbox" name="remember" value="1"> Keep me signed in on this terminal</label>
                <button class="btn btn-block btn-lg" type="submit">Sign in</button>
            </form>

            <div class="demo-box">
                <strong>Demo accounts</strong> — password <code>password</code> for all.
                <div class="demo-row"><span>Owner</span><code>owner@gitgadgets.test</code></div>
                <div class="demo-row"><span>Manager (PIN 1234)</span><code>manager@gitgadgets.test</code></div>
                <div class="demo-row"><span>Cashier</span><code>cashier@gitgadgets.test</code></div>
                <div class="demo-row"><span>Storekeeper</span><code>store@gitgadgets.test</code></div>
                <div class="demo-row"><span>Accountant</span><code>accounts@gitgadgets.test</code></div>
            </div>
        </div>
    </section>
</body>
</html>
