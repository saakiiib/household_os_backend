@extends('web.layout')

@section('title', 'Sign In - HouseholdOS')

@section('content')
<section class="hos-section">
    <div class="hos-container">
        <div class="hos-back-row">
            <a class="hos-back" href="https://householdosapp.com/">← Back</a>
        </div>
        <div class="hos-card hos-auth-card" style="margin-top:0;">
            <form method="POST" action="{{ route('web.login.store') }}">
                @csrf
                <div class="hos-field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
                    @error('email')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="Enter password" required>
                    @error('password')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="remember" id="remember" value="1" style="width:auto;">
                    <label for="remember" style="margin:0;font-weight:500;">Remember me</label>
                </div>
                <button type="submit" class="hos-btn hos-btn-primary hos-btn-block hos-btn-large">Sign In</button>
            </form>
            <p style="text-align:center;color:var(--hos-muted);margin:16px 0 0;font-size:14px;">No account yet? <a href="{{ route('web.register') }}" style="color:var(--hos-blue);font-weight:700;">Create one</a></p>
        </div>
    </div>
</section>
@endsection
