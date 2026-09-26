@extends('web.layout')

@section('title', 'Verify Email - HouseholdOS')

@section('content')
<section class="hos-section">
    <div class="hos-container">
        <div class="hos-card hos-auth-card" style="margin-top:0;">
            <div style="text-align:center;margin-bottom:18px;">
                <div class="hos-result-icon ok" style="margin-bottom:12px;">✉</div>
                <h3 style="margin:0 0 6px;">Check your email</h3>
                <p style="color:var(--hos-muted);font-size:14px;margin:0;">We sent a 6-digit code to<br><strong>{{ auth()->user()->email }}</strong></p>
            </div>

            @if (session('resent'))
                <div class="hos-alert hos-alert-info">A new code has been sent.</div>
            @endif

            <form method="POST" action="{{ route('verification.verify') }}">
                @csrf
                <div class="hos-field">
                    <label for="code">Verification code</label>
                    <input id="code" type="text" name="code" inputmode="numeric" maxlength="6" placeholder="••••••" required autofocus
                           style="text-align:center;letter-spacing:8px;font-size:22px;font-weight:800;">
                    @error('code')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="hos-btn hos-btn-primary hos-btn-block hos-btn-large">Verify Email</button>
            </form>

            <form method="POST" action="{{ route('verification.resend') }}" style="margin-top:10px;">
                @csrf
                <button type="submit" class="hos-btn hos-btn-soft hos-btn-block">Resend code</button>
            </form>

            <form method="POST" action="{{ route('web.logout') }}" style="margin-top:14px;text-align:center;">
                @csrf
                <button type="submit" style="background:none;border:0;color:var(--hos-muted);font-size:13px;cursor:pointer;">Wrong email? Start over</button>
            </form>
        </div>
    </div>
</section>
@endsection
