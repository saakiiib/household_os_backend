@extends('web.layout')

@section('title', 'Choose a Plan - HouseholdOS')

@php
    $hero = $plans->firstWhere('is_popular', true) ?? $plans->firstWhere('slug', 'complete');
    $modules = $hero ? $plans->reject(fn ($p) => $p->id === $hero->id)->values() : $plans;
    $providerLabels = ['apple' => 'App Store', 'google_play' => 'Google Play', 'google' => 'Google Play', 'stripe' => 'card (Stripe)', 'paypal' => 'PayPal'];
    $hasPaidSub = $subscription && $subscription->status === 'active' && ($subscription->plan_status ?? null) === 'paid';
    $subProvider = $hasPaidSub ? ($providerLabels[strtolower($subscription->provider ?? '')] ?? ucfirst($subscription->provider ?? '')) : null;
    $renewDate = $hasPaidSub ? ($subscription->current_period_end ?? $subscription->expires_at) : null;
    $isCurrent = fn ($plan) => $hasPaidSub && ($subscription->paid_plan ?? null) === $plan->slug;
@endphp

@section('content')
<section class="hos-page-hero">
    <div class="hos-container">
        <span class="hos-kicker">Simple, flexible pricing</span>
        <h1>Everything Your Household Needs, in One Plan</h1>
        <p>Subscribing for {{ $household?->name ?? 'your household' }} ({{ auth()->user()->email }}). One subscription covers the whole household.</p>
    </div>
</section>

<section class="hos-section">
    <div class="hos-container">
        @if ($errors->any())
            <div class="hos-alert hos-alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($hasPaidSub)
            <div class="hos-alert hos-alert-warn">
                <strong>You're subscribed to {{ $subscription->paid_plan ? ucfirst($subscription->paid_plan) : 'a plan' }}</strong>,
                billed via {{ $subProvider }}@if ($renewDate), renewing {{ $renewDate->format('d M Y') }}@endif.
                Buying a new plan here replaces it — cancel the old one first to avoid double billing.
            </div>
        @elseif ($subscription && $subscription->status === 'trial')
            <div class="hos-alert hos-alert-info">
                Your free trial runs until {{ ($subscription->trial_ends_at ?? $subscription->expires_at)?->format('d M Y') }}.
                Subscribing now starts paid billing after your trial days end.
            </div>
        @endif

        <div class="hos-toggle-row">
            <div class="hos-billing-toggle" role="group" aria-label="Billing frequency">
                <button type="button" class="billing-option active" data-billing="monthly" aria-pressed="true"><b>Monthly</b><small>Pay as you go</small></button>
                <button type="button" class="billing-option" data-billing="yearly" aria-pressed="false"><b>Yearly</b><small>12 monthly payments</small></button>
            </div>
        </div>

        @if ($hero)
            <article class="hos-complete-card">
                <div class="hos-ribbon">★ Best Household Value</div>
                <div class="hos-complete-top">
                    <div>
                        <span class="hos-plan-kicker">Everything together</span>
                        <h3>{{ $hero->name }}</h3>
                        <p>{{ $hero->description ?? 'One connected home for tasks, renewals and important household documents.' }}</p>
                    </div>
                    <div>
                        <div class="hos-price-line">
                            <span class="hos-price" data-monthly="£{{ number_format($hero->monthly_price, 2) }}" data-yearly="£{{ number_format($hero->annual_price, 2) }}">£{{ number_format($hero->monthly_price, 2) }}</span>
                            <span class="hos-period">/month</span>
                        </div>
                        <p class="hos-after-copy">after any free trial · cancel anytime</p>
                        @if ($isCurrent($hero))
                            <span class="hos-current-tag" style="background:#dcfce7;color:#166534;">✓ Current plan</span>
                        @else
                            <div style="display:grid;gap:8px;min-width:240px;">
                                <form method="POST" action="{{ route('billing.subscribe') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $hero->id }}">
                                    <input type="hidden" name="billing_period" value="monthly">
                                    <button type="submit" class="hos-btn hos-btn-primary hos-btn-block">Subscribe Monthly</button>
                                </form>
                                <form method="POST" action="{{ route('billing.subscribe') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $hero->id }}">
                                    <input type="hidden" name="billing_period" value="annual">
                                    <button type="submit" class="hos-btn hos-btn-soft hos-btn-block">Subscribe Yearly</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
                @if (is_array($hero->features) && count($hero->features))
                    <div class="hos-included-title">What's included</div>
                    <p class="hos-included-sub">Everything works together inside one household subscription.</p>
                    <div class="hos-included-grid">
                        @foreach (array_slice($hero->features, 0, 3) as $i => $feature)
                            <div class="hos-included">
                                <h4>{{ is_string($feature) ? $feature : json_encode($feature) }}</h4>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        @endif

        @if ($modules->count())
            <div class="hos-module-section">
                <h3>{{ $hero ? 'Need less? Choose just one module' : 'Choose your plan' }}</h3>
                <p>Each module is available separately for households that only need one part of HouseholdOS.</p>
                <div class="hos-module-grid">
                    @foreach ($modules as $plan)
                        <article class="hos-module-card">
                            <span class="hos-module-icon">{{ $loop->index === 0 ? '✓' : ($loop->index === 1 ? '◷' : '▰') }}</span>
                            <h4>{{ $plan->name }}</h4>
                            <p>{{ $plan->description ?? '' }}</p>
                            <div class="hos-module-price">
                                <span class="hos-price" data-monthly="£{{ number_format($plan->monthly_price, 2) }}" data-yearly="£{{ number_format($plan->annual_price, 2) }}">£{{ number_format($plan->monthly_price, 2) }}</span><small class="hos-period">/month</small>
                            </div>
                            @if (is_array($plan->features) && count($plan->features))
                                <ul class="small text-muted ps-3 mb-2" style="font-size:13px;color:var(--hos-muted);">
                                    @foreach (array_slice($plan->features, 0, 4) as $feature)
                                        <li>{{ is_string($feature) ? $feature : json_encode($feature) }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($isCurrent($plan))
                                <span class="hos-current-tag">✓ Current plan</span>
                            @else
                                <form method="POST" action="{{ route('billing.subscribe') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <input type="hidden" name="billing_period" value="monthly">
                                    <button type="submit" class="hos-btn hos-btn-primary hos-btn-block">Subscribe Monthly</button>
                                </form>
                                <form method="POST" action="{{ route('billing.subscribe') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <input type="hidden" name="billing_period" value="annual">
                                    <button type="submit" class="hos-btn hos-btn-outline hos-btn-block">Subscribe Yearly</button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        @if (!$hero && !$modules->count())
            <div class="hos-alert hos-alert-danger" style="margin-top:20px;">No paid plans are available right now. Please try again later.</div>
        @endif

        <div class="hos-reassurance">
            <div><b>Private and secure</b><small>Built for household data</small></div>
            <div><b>Cancel anytime</b><small>No long-term lock-in</small></div>
            <div><b>Auto-renewed</b><small>Monthly or yearly billing</small></div>
            <div><b>One household subscription</b><small>Invite family members</small></div>
        </div>
        <p class="hos-note">Prices in GBP. Your card is charged by Stripe and renews automatically until cancelled.</p>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.billing-option').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.billing-option').forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');

        var yearly = btn.dataset.billing === 'yearly';
        document.querySelectorAll('.hos-price').forEach(function (el) {
            el.textContent = yearly ? el.dataset.yearly : el.dataset.monthly;
        });
        document.querySelectorAll('.hos-period').forEach(function (el) {
            el.textContent = yearly ? '/year' : '/month';
        });
        document.querySelectorAll('form[action="{{ route('billing.subscribe') }}"] input[name="billing_period"]').forEach(function (input) {
            input.value = yearly ? 'annual' : 'monthly';
        });
    });
});
</script>
@endpush
