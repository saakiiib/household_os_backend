@extends('web.layout')

@section('title', 'Payment Successful - HouseholdOS')

@section('content')
<section class="hos-page-hero">
    <div class="hos-container">
        <span class="hos-kicker">Subscription</span>
        <h1>@if ($pending ?? false) Payment received @else Payment successful @endif</h1>
    </div>
</section>

<section class="hos-section">
    <div class="hos-container">
        <div class="hos-card hos-auth-card" style="text-align:center;">
            @if ($pending ?? false)
                <div class="hos-result-icon warn">…</div>
                <h3>Payment received</h3>
                <p style="color:var(--hos-muted);">Your payment is being confirmed. Your subscription will activate shortly — please check the app in a minute.</p>
            @else
                <div class="hos-result-icon ok">✓</div>
                <h3>You're subscribed</h3>
                <p style="color:var(--hos-muted);">Your subscription is now active for your whole household. It renews automatically.</p>
            @endif

            <div class="hos-alert hos-alert-info" style="text-align:left;margin-top:18px;">
                <strong>Open the app with this exact account:</strong><br>
                Email: <strong>{{ $email ?? '' }}</strong>
                @if (!empty($householdName))<br>Household: <strong>{{ $householdName }}</strong>@endif
                <br><span style="font-size:13px;">Use email + password login — not Google/Apple — unless that email is already linked.</span>
            </div>

            <p style="font-weight:800;margin:18px 0 10px;">Download the app</p>
            <div style="display:grid;gap:8px;">
                <a class="hos-btn hos-btn-dark hos-btn-block" href="{{ $appStoreUrl ?? 'https://apps.apple.com/app/household-os/id000000000' }}">Download on the App Store</a>
                <a class="hos-btn hos-btn-primary hos-btn-block" href="{{ $playStoreUrl ?? 'https://play.google.com/store/apps/details?id=com.mentosoftware.householdos' }}">Get it on Google Play</a>
            </div>

            <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-soft" style="margin-top:14px;">Back to Plans</a>
        </div>
    </div>
</section>
@endsection
