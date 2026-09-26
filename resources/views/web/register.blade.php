@extends('web.layout')

@section('title', 'Create Account - HouseholdOS')

@section('content')
<section class="hos-section">
    <div class="hos-container">
        <div class="hos-back-row">
            <a class="hos-back" href="https://householdosapp.com/">← Back</a>
        </div>
        <div class="hos-card hos-auth-card" style="margin-top:0;">
            <form method="POST" action="{{ route('web.register.store') }}">
                @csrf
                <div class="hos-field-row">
                    <div class="hos-field">
                        <label for="first_name">First name</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" placeholder="Jane" required autofocus>
                        @error('first_name')<div class="hos-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="hos-field">
                        <label for="last_name">Last name</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Doe" required>
                        @error('last_name')<div class="hos-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="hos-field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
                    @error('email')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field">
                    <label for="household_name">Household name</label>
                    <input id="household_name" type="text" name="household_name" value="{{ old('household_name') }}" placeholder="The Doe Family" required>
                    @error('household_name')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="Minimum 8 characters" required>
                    @error('password')<div class="hos-error">{{ $message }}</div>@enderror
                </div>
                <div class="hos-field">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat password" required>
                </div>
                <button type="submit" class="hos-btn hos-btn-primary hos-btn-block hos-btn-large">Create Account</button>
            </form>
            <p style="text-align:center;color:var(--hos-muted);margin:16px 0 0;font-size:14px;">Already have an account? <a href="{{ route('web.login') }}" style="color:var(--hos-blue);font-weight:700;">Sign in</a></p>
        </div>
    </div>
</section>
@endsection
