@extends('web.layout')

@section('title', 'My Account - HouseholdOS')

@php
    $providerLabels = ['apple' => 'App Store', 'google_play' => 'Google Play', 'google' => 'Google Play', 'stripe' => 'card (Stripe)', 'paypal' => 'PayPal'];
    $hasPaidSub = $subscription && $subscription->status === 'active' && ($subscription->plan_status ?? null) === 'paid';
    $isTrial = $subscription && $subscription->status === 'trial';
    $endDate = $subscription ? ($subscription->current_period_end ?? $subscription->expires_at) : null;
    $daysLeft = $endDate && $endDate->isFuture() ? now()->diffInDays($endDate) : 0;
    $trialEnd = $subscription ? ($subscription->trial_ends_at ?? $subscription->expires_at) : null;
    $trialDaysLeft = $trialEnd && $trialEnd->isFuture() ? now()->diffInDays($trialEnd) : 0;
    $fmtLimit = fn ($used, $limit) => $limit === null ? $used . ' / ∞' : $used . ' / ' . $limit;
    $fmtBytes = function ($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        return number_format($bytes / 1024, 0) . ' KB';
    };
@endphp

@section('content')
<section class="hos-page-hero">
    <div class="hos-container">
        <span class="hos-kicker">My account</span>
        <h1>Hello, {{ $user->first_name }}</h1>
        <p>{{ $user->email }}@if ($household) · {{ $household->name }}@endif</p>
        <p style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('billing.plans') }}" style="color:var(--hos-blue);font-weight:700;">← Plans</a>
        </p>
    </div>
</section>

<section class="hos-section">
    <div class="hos-container">
        @if (session('status'))
            <div class="hos-alert hos-alert-info">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="hos-alert hos-alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="hos-card" style="margin-bottom:20px;">
            <h3 style="margin:0 0 4px;">Subscription</h3>
            @if ($hasPaidSub)
                <p style="margin:0 0 12px;font-size:14px;">
                    <span class="hos-current-tag" style="background:#dcfce7;color:#166534;">✓ ACTIVE</span>
                    <strong style="margin-left:8px;">{{ $subscription->paid_plan ? ucfirst($subscription->paid_plan) : 'Paid plan' }}</strong>
                </p>
                <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:12px;">
                    <tbody>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 4px;color:var(--hos-muted);">Status</td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">Active</td>
                        </tr>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 4px;color:var(--hos-muted);">Billing</td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">{{ ucfirst($subscription->billing_period ?? '') }} via {{ $providerLabels[strtolower($subscription->provider ?? '')] ?? ucfirst($subscription->provider ?? '') }}</td>
                        </tr>
                        @if ($endDate)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:8px 4px;color:var(--hos-muted);">Next renewal</td>
                                <td style="padding:8px 4px;text-align:right;font-weight:600;">{{ $endDate->format('d M Y') }}</td>
                            </tr>
                        @endif
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 4px;color:var(--hos-muted);">Auto-renew</td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">{{ $subscription->auto_renew ? 'On' : 'Off' }}</td>
                        </tr>
                        @if ($daysLeft > 0)
                            <tr>
                                <td style="padding:8px 4px;color:var(--hos-muted);">Days remaining</td>
                                <td style="padding:8px 4px;text-align:right;font-weight:600;">{{ $daysLeft }} days</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-outline">Change plan</a>
            @elseif ($isTrial)
                <p style="margin:0 0 12px;font-size:14px;">
                    <span class="hos-current-tag" style="background:#dbeafe;color:#1d4ed8;">FREE TRIAL</span>
                    @if ($trialDaysLeft > 0)<strong style="margin-left:8px;">{{ $trialDaysLeft }} days left</strong>@endif
                </p>
                <p style="margin:0 0 12px;color:var(--hos-muted);font-size:14px;">
                    Trial ends {{ $trialEnd?->format('d M Y') }}. Subscribing now starts paid billing after your trial days end.
                </p>
                <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-primary">Subscribe</a>
            @else
                <p style="margin:0 0 12px;color:var(--hos-muted);font-size:14px;">You're on the free plan.</p>
                <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-primary">View plans</a>
            @endif
        </div>

        @if ($usage)
            <div class="hos-card" style="margin-bottom:20px;">
                <h3 style="margin:0 0 12px;">Usage</h3>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tbody>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 4px;color:var(--hos-muted);">Tasks</td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">{{ $fmtLimit($usage['usage']['tasks'], $usage['limits']['tasks']) }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 4px;color:var(--hos-muted);">Renewals</td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">{{ $fmtLimit($usage['usage']['renewals'], $usage['limits']['renewals']) }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 4px;color:var(--hos-muted);">Document storage</td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">{{ $fmtBytes($usage['usage']['documents_bytes']) }} / {{ $fmtBytes($usage['limits']['documents_bytes']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        <div class="hos-card" style="margin-bottom:20px;">
            <h3 style="margin:0 0 12px;">Profile</h3>
            <form method="POST" action="{{ route('account.profile') }}">
                @csrf
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
                <div class="hos-field">
                    <label for="phone">Phone</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Optional">
                    @error('phone')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field">
                    <label>Email</label>
                    <input type="email" value="{{ $user->email }}" disabled>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;font-size:13px;">
                    <span class="hos-current-tag" style="background:#f1f5f9;color:#334155;">Google: {{ in_array('google', $linkedProviders) ? '✓ Connected' : 'Not connected' }}</span>
                    <span class="hos-current-tag" style="background:#f1f5f9;color:#334155;">Apple: {{ in_array('apple', $linkedProviders) ? '✓ Connected' : 'Not connected' }}</span>
                </div>
                <button type="submit" class="hos-btn hos-btn-primary">Save profile</button>
            </form>
        </div>

        <div class="hos-card" style="margin-bottom:20px;">
            <h3 style="margin:0 0 12px;">Change password</h3>
            <form method="POST" action="{{ route('account.password') }}">
                @csrf
                <div class="hos-field">
                    <label for="current_password">Current password</label>
                    <input id="current_password" type="password" name="current_password" required>
                    @error('current_password')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field">
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" placeholder="Min 6 characters" required>
                    @error('password')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                </div>
                <button type="submit" class="hos-btn hos-btn-primary">Change password</button>
            </form>
        </div>

        <form method="POST" action="{{ route('web.logout') }}">
            @csrf
            <button type="submit" class="hos-btn hos-btn-outline hos-btn-block">Sign out</button>
        </form>
    </div>
</section>
@endsection
