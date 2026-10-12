@extends('web.layout')

@section('title', 'My Account - HouseholdOS')

@php
    $providerLabels = ['apple' => 'App Store', 'google_play' => 'Google Play', 'google' => 'Google Play', 'stripe' => 'card (Stripe)', 'paypal' => 'PayPal'];
    $hasPaidSub = $subscription && $subscription->status === 'active' && ($subscription->plan_status ?? null) === 'paid';
    $isTrial = $subscription && $subscription->status === 'trial';
    $endDate = $subscription ? ($subscription->current_period_end ?? $subscription->expires_at) : null;
    $daysLeft = $endDate && $endDate->isFuture() ? (int) now()->diffInDays($endDate) : 0;
    $trialEnd = $subscription ? ($subscription->trial_ends_at ?? $subscription->expires_at) : null;
    $trialDaysLeft = $trialEnd && $trialEnd->isFuture() ? (int) now()->diffInDays($trialEnd) : 0;
    $fmtLimit = fn ($used, $limit) => $limit === null ? $used . ' / ∞' : $used . ' / ' . $limit;
    $fmtBytes = function ($bytes) {
        $bytes = (int) ($bytes ?? 0);
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        return number_format($bytes / 1024, 0) . ' KB';
    };
    $pct = fn ($used, $limit) => ($limit !== null && $limit > 0) ? (int) min(100, round(((int) ($used ?? 0)) / $limit * 100)) : null;
    $storagePct = $usage ? $pct($usage['usage']['documents_bytes'] ?? 0, $usage['limits']['documents_bytes'] ?? null) : null;

    $initialTab = 'subscription';
    if ($errors->has('current_password') || $errors->has('password') || session('status') === 'Password changed.') {
        $initialTab = 'security';
    } elseif ($errors->has('first_name') || $errors->has('last_name') || $errors->has('phone') || session('status') === 'Profile updated.') {
        $initialTab = 'profile';
    }
@endphp

@section('content')
<style>
.hos-portal { display:grid; grid-template-columns:248px minmax(0,1fr); gap:22px; align-items:start; max-width:1020px; margin:0 auto; }
.hos-portal-menu { background:#fff; border:1px solid var(--hos-line); border-radius:var(--hos-radius); box-shadow:var(--hos-shadow); padding:10px; display:flex; flex-direction:column; gap:4px; position:sticky; top:76px; }
.hos-portal-menu button, .hos-portal-menu .hos-menu-signout { display:flex; align-items:center; gap:10px; width:100%; text-align:left; border:0; background:transparent; cursor:pointer; font-family:inherit; font-size:14.5px; font-weight:700; color:var(--hos-muted); padding:12px 14px; border-radius:10px; text-decoration:none; }
.hos-portal-menu button:hover { background:#f1f6fd; color:var(--hos-navy); }
.hos-portal-menu button.active { background:#e8f1fd; color:var(--hos-blue); }
.hos-menu-dot { width:8px; height:8px; border-radius:50%; background:currentColor; opacity:.4; flex:none; }
.hos-portal-menu button.active .hos-menu-dot { opacity:1; }
.hos-menu-sep { border-top:1px solid var(--hos-line); margin:8px 4px; }
.hos-menu-signoutform { margin:0; }
.hos-menu-signout { color:#b91c1c; }
.hos-menu-signout:hover { background:#fef2f2; }
.hos-portal-panels { min-width:0; }
.hos-tab-panel { display:none; }
.hos-tab-panel.active { display:block; }
.hos-panel-card { background:#fff; border:1px solid var(--hos-line); border-radius:var(--hos-radius); box-shadow:var(--hos-shadow); padding:28px; }
.hos-panel-card h3 { margin:0 0 4px; font-size:20px; }
.hos-panel-sub { color:var(--hos-muted); font-size:14px; margin:0 0 18px; }
.hos-plan-name { font-family:'Outfit',sans-serif; font-size:30px; font-weight:800; margin:10px 0 2px; }
.hos-pill { display:inline-block; font-weight:800; font-size:12px; letter-spacing:.05em; padding:6px 14px; border-radius:999px; }
.hos-pill-green { background:#dcfce7; color:#166534; }
.hos-pill-blue { background:#dbeafe; color:#1d4ed8; }
.hos-pill-grey { background:#f1f5f9; color:#475569; }
.hos-kv-group { margin:14px 0 4px; }
.hos-kv { display:flex; justify-content:space-between; align-items:center; gap:18px; padding:11px 2px; border-bottom:1px solid #f1f5f9; font-size:14.5px; }
.hos-kv-group .hos-kv:last-child { border-bottom:0; }
.hos-kv > span { color:var(--hos-muted); flex:none; }
.hos-kv > strong { font-weight:700; text-align:right; min-width:0; overflow-wrap:anywhere; }
.hos-panel-actions { margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }
.hos-invite-code { font-family:ui-monospace,Menlo,Consolas,monospace; background:#f1f5f9; padding:5px 12px; border-radius:8px; letter-spacing:.1em; font-weight:800; }
.hos-mini-btn { border:1px solid var(--hos-line); background:#fff; border-radius:8px; font-family:inherit; font-size:12.5px; font-weight:700; color:var(--hos-blue); padding:5px 12px; cursor:pointer; margin-left:8px; }
.hos-mini-btn:hover { border-color:var(--hos-blue); }
.hos-usage-row { padding:13px 2px; border-bottom:1px solid #f1f5f9; font-size:14.5px; }
.hos-usage-row:last-child { border-bottom:0; }
.hos-usage-top { display:flex; justify-content:space-between; gap:16px; }
.hos-usage-top span { color:var(--hos-muted); font-weight:600; }
.hos-usage-top strong { font-weight:700; }
.hos-meter { height:8px; background:#eef3fa; border-radius:999px; overflow:hidden; margin-top:9px; }
.hos-meter > i { display:block; height:100%; background:linear-gradient(90deg,var(--hos-blue),#38a1ff); border-radius:999px; }
.hos-meter.warn > i { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
.hos-linked-row { display:flex; gap:8px; flex-wrap:wrap; margin:2px 0 16px; }
@media (max-width: 900px) {
  .hos-portal { grid-template-columns:1fr; }
  .hos-portal-menu { flex-direction:row; overflow-x:auto; position:static; padding:8px; }
  .hos-portal-menu button { white-space:nowrap; width:auto; }
  .hos-menu-sep { display:none; }
  .hos-menu-signoutform { margin-left:auto; }
  .hos-panel-card { padding:22px 18px; }
}
</style>

<section class="hos-page-hero">
    <div class="hos-container">
        <span class="hos-kicker">My account</span>
        <h1>Hello, {{ $user->first_name }}</h1>
        <p>{{ $user->email }}@if ($household) · {{ $household->name }}@endif</p>
        <p style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center;">
            <a href="{{ route('billing.plans') }}" style="color:#9fc2f2;font-weight:700;text-decoration:none;">← Plans</a>
        </p>
    </div>
</section>

<section class="hos-section">
    <div class="hos-container">
        @if (session('status'))
            <div class="hos-alert hos-alert-info" style="max-width:1020px;margin:0 auto 18px;">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="hos-alert hos-alert-danger" style="max-width:1020px;margin:0 auto 18px;">{{ $errors->first() }}</div>
        @endif

        <div class="hos-portal">
            <aside class="hos-portal-menu">
                <button type="button" data-hos-tab="subscription" class="{{ $initialTab === 'subscription' ? 'active' : '' }}"><span class="hos-menu-dot"></span>Subscription</button>
                <button type="button" data-hos-tab="usage" class="{{ $initialTab === 'usage' ? 'active' : '' }}"><span class="hos-menu-dot"></span>Usage</button>
                <button type="button" data-hos-tab="profile" class="{{ $initialTab === 'profile' ? 'active' : '' }}"><span class="hos-menu-dot"></span>Profile</button>
                <button type="button" data-hos-tab="security" class="{{ $initialTab === 'security' ? 'active' : '' }}"><span class="hos-menu-dot"></span>Security</button>
                <div class="hos-menu-sep"></div>
                <form method="POST" action="{{ route('web.logout') }}" class="hos-menu-signoutform">
                    @csrf
                    <button type="submit" class="hos-menu-signout" style="width:100%;"><span class="hos-menu-dot"></span>Sign out</button>
                </form>
            </aside>

            <div class="hos-portal-panels">
                <section class="hos-tab-panel {{ $initialTab === 'subscription' ? 'active' : '' }}" data-hos-panel="subscription">
                    <div class="hos-panel-card">
                        <h3>Subscription</h3>
                        <p class="hos-panel-sub">One subscription covers your whole household.</p>
                        @if ($hasPaidSub)
                            <span class="hos-pill hos-pill-green">✓ ACTIVE</span>
                            <div class="hos-plan-name">{{ $subscription->paid_plan ? ucfirst($subscription->paid_plan) : 'Paid plan' }}</div>
                            <div class="hos-kv-group">
                                <div class="hos-kv"><span>Status</span><strong>Active</strong></div>
                                <div class="hos-kv"><span>Billing</span><strong>{{ ucfirst($subscription->billing_period ?? '') }} via {{ $providerLabels[strtolower($subscription->provider ?? '')] ?? ucfirst($subscription->provider ?? '') }}</strong></div>
                                @if ($endDate)
                                    <div class="hos-kv"><span>Next renewal</span><strong>{{ $endDate->format('d M Y') }}</strong></div>
                                @endif
                                <div class="hos-kv"><span>Auto-renew</span><strong>{{ $subscription->auto_renew ? 'On' : 'Off' }}</strong></div>
                                @if ($daysLeft > 0)
                                    <div class="hos-kv"><span>Days remaining</span><strong>{{ $daysLeft }} days</strong></div>
                                @endif
                                @if ($household)
                                    <div class="hos-kv"><span>Household</span><strong>{{ $household->name }}</strong></div>
                                    <div class="hos-kv"><span>Invite code</span><strong><span class="hos-invite-code">{{ $household->invite_code ?? '—' }}</span>@if(!empty($household->invite_code))<button type="button" class="hos-mini-btn" id="hos-copy-invite" data-code="{{ $household->invite_code }}">Copy</button>@endif</strong></div>
                                @endif
                            </div>
                            <div class="hos-panel-actions">
                                <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-outline">Change plan</a>
                            </div>
                        @elseif ($isTrial)
                            <span class="hos-pill hos-pill-blue">FREE TRIAL</span>
                            <div class="hos-plan-name">Complete</div>
                            <div class="hos-kv-group">
                                @if ($trialDaysLeft > 0)
                                    <div class="hos-kv"><span>Days left</span><strong>{{ $trialDaysLeft }} days</strong></div>
                                @endif
                                <div class="hos-kv"><span>Trial ends</span><strong>{{ $trialEnd?->format('d M Y') }}</strong></div>
                            </div>
                            <p class="hos-panel-sub" style="margin-top:12px;">Subscribing now starts paid billing after your trial days end.</p>
                            <div class="hos-panel-actions">
                                <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-primary">Subscribe</a>
                            </div>
                        @else
                            <span class="hos-pill hos-pill-grey">FREE</span>
                            <div class="hos-plan-name">Free plan</div>
                            <p class="hos-panel-sub" style="margin-top:12px;">Upgrade to unlock more tasks, renewals and document storage for your household.</p>
                            <div class="hos-panel-actions">
                                <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-primary">View plans</a>
                            </div>
                        @endif
                    </div>
                </section>

                @if ($usage)
                    <section class="hos-tab-panel {{ $initialTab === 'usage' ? 'active' : '' }}" data-hos-panel="usage">
                        <div class="hos-panel-card">
                            <h3>Usage</h3>
                            <p class="hos-panel-sub">What your household has used so far.</p>
                            @php
                                $tasksPct = $pct($usage['usage']['tasks'] ?? 0, $usage['limits']['tasks'] ?? null);
                                $renewalsPct = $pct($usage['usage']['renewals'] ?? 0, $usage['limits']['renewals'] ?? null);
                            @endphp
                            <div class="hos-usage-row">
                                <div class="hos-usage-top"><span>Tasks</span><strong>{{ $fmtLimit($usage['usage']['tasks'] ?? 0, $usage['limits']['tasks'] ?? null) }}</strong></div>
                                @if ($tasksPct !== null)<div class="hos-meter{{ $tasksPct >= 80 ? ' warn' : '' }}"><i style="width:{{ $tasksPct }}%"></i></div>@endif
                            </div>
                            <div class="hos-usage-row">
                                <div class="hos-usage-top"><span>Renewals</span><strong>{{ $fmtLimit($usage['usage']['renewals'] ?? 0, $usage['limits']['renewals'] ?? null) }}</strong></div>
                                @if ($renewalsPct !== null)<div class="hos-meter{{ $renewalsPct >= 80 ? ' warn' : '' }}"><i style="width:{{ $renewalsPct }}%"></i></div>@endif
                            </div>
                            <div class="hos-usage-row">
                                <div class="hos-usage-top"><span>Document storage</span><strong>{{ $fmtBytes($usage['usage']['documents_bytes'] ?? 0) }} / {{ $fmtBytes($usage['limits']['documents_bytes'] ?? 0) }}</strong></div>
                                @if ($storagePct !== null)<div class="hos-meter{{ $storagePct >= 80 ? ' warn' : '' }}"><i style="width:{{ $storagePct }}%"></i></div>@endif
                            </div>
                        </div>
                    </section>
                @endif

                <section class="hos-tab-panel {{ $initialTab === 'profile' ? 'active' : '' }}" data-hos-panel="profile">
                    <div class="hos-panel-card">
                        <h3>Profile</h3>
                        <p class="hos-panel-sub">How your household sees you.</p>
                        <form method="POST" action="{{ route('account.profile') }}">
                            @csrf
                            <div class="hos-field-row">
                                <div class="hos-field">
                                    <label for="first_name">First name</label>
                                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required>
                                    @error('first_name')<div class="hos-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="hos-field">
                                    <label for="last_name">Last name</label>
                                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}">
                                    @error('last_name')<div class="hos-error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="hos-field">
                                <label for="phone">Phone</label>
                                <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Optional">
                                @error('phone')<div class="hos-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="hos-field">
                                <label>Email</label>
                                <input type="email" value="{{ $user->email }}" disabled>
                            </div>
                            <div class="hos-linked-row">
                                <span class="hos-pill {{ in_array('google', $linkedProviders) ? 'hos-pill-green' : 'hos-pill-grey' }}">Google: {{ in_array('google', $linkedProviders) ? '✓ Connected' : 'Not connected' }}</span>
                                <span class="hos-pill {{ in_array('apple', $linkedProviders) ? 'hos-pill-green' : 'hos-pill-grey' }}">Apple: {{ in_array('apple', $linkedProviders) ? '✓ Connected' : 'Not connected' }}</span>
                            </div>
                            <div class="hos-panel-actions">
                                <button type="submit" class="hos-btn hos-btn-primary">Save profile</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="hos-tab-panel {{ $initialTab === 'security' ? 'active' : '' }}" data-hos-panel="security">
                    <div class="hos-panel-card">
                        <h3>Security</h3>
                        <p class="hos-panel-sub">Change the password you use to sign in.</p>
                        <form method="POST" action="{{ route('account.password') }}">
                            @csrf
                            <div class="hos-field">
                                <label for="current_password">Current password</label>
                                <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                                @error('current_password')<div class="hos-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="hos-field-row">
                                <div class="hos-field">
                                    <label for="password">New password</label>
                                    <input id="password" type="password" name="password" placeholder="Minimum 8 characters" required autocomplete="new-password">
                                    @error('password')<div class="hos-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="hos-field">
                                    <label for="password_confirmation">Confirm new password</label>
                                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                                </div>
                            </div>
                            <div class="hos-panel-actions">
                                <button type="submit" class="hos-btn hos-btn-primary">Change password</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function () {
    var tabs = ['subscription', 'usage', 'profile', 'security'];
    var buttons = document.querySelectorAll('[data-hos-tab]');
    var panels = document.querySelectorAll('[data-hos-panel]');
    function openTab(name) {
        buttons.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-hos-tab') === name); });
        panels.forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-hos-panel') === name); });
    }
    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            var name = b.getAttribute('data-hos-tab');
            openTab(name);
            if (history.replaceState) { history.replaceState(null, '', '#' + name); }
        });
    });
    var hash = (location.hash || '').replace('#', '');
    if (tabs.indexOf(hash) !== -1 && document.querySelector('[data-hos-panel="' + hash + '"]')) { openTab(hash); }
    var copyBtn = document.getElementById('hos-copy-invite');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var code = copyBtn.getAttribute('data-code') || '';
            function done() { copyBtn.textContent = 'Copied'; setTimeout(function () { copyBtn.textContent = 'Copy'; }, 1500); }
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(code).then(done, done); }
            else {
                var t = document.createElement('textarea');
                t.value = code;
                document.body.appendChild(t);
                t.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(t);
                done();
            }
        });
    }
})();
</script>
@endpush
@endsection
