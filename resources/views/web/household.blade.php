@extends('web.layout')

@section('title', 'Create Your Household - HouseholdOS')

@section('content')
<section class="hos-section">
    <div class="hos-container">
        <div class="hos-back-row">
            <a class="hos-back" href="https://householdosapp.com/">← Back</a>
        </div>
        <div class="hos-card hos-auth-card" style="margin-top:0;">
            <h2 style="margin:0 0 6px;">Create your household</h2>
            <p style="color:var(--hos-muted);margin:0 0 18px;font-size:14px;">One subscription covers your whole household. You can invite members later from the app.</p>
            <form method="POST" action="{{ route('household.store') }}">
                @csrf
                <div class="hos-field">
                    <label for="household_name">Household name</label>
                    <input id="household_name" type="text" name="household_name" value="{{ old('household_name') }}" placeholder="The Doe Family" required autofocus>
                    @error('household_name')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="hos-btn hos-btn-primary hos-btn-block hos-btn-large">Continue to plans</button>
            </form>
        </div>
    </div>
</section>
@endsection
