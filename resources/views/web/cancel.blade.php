@extends('web.layout')

@section('title', 'Payment Cancelled - HouseholdOS')

@section('content')
<section class="hos-page-hero">
    <div class="hos-container">
        <span class="hos-kicker">Subscription</span>
        <h1>Payment cancelled</h1>
    </div>
</section>

<section class="hos-section">
    <div class="hos-container">
        <div class="hos-card hos-auth-card" style="text-align:center;">
            <div class="hos-result-icon warn">×</div>
            <h3>No charge was made</h3>
            <p style="color:var(--hos-muted);">You can pick a plan whenever you're ready.</p>
            <a href="{{ route('billing.plans') }}" class="hos-btn hos-btn-primary" style="margin-top:10px;">Back to Plans</a>
        </div>
    </div>
</section>
@endsection
