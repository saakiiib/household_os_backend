@extends('auth.master')

@section('title', 'Reset Password')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card mt-4">
            <div class="card-body p-4">

                <div class="text-center mt-2">
                    <h5 class="text-primary">Reset Password</h5>
                    <p class="text-muted">Enter your new password to reset your account password.</p>
                </div>

                <div class="p-2 mt-4">
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   name="email" value="{{ $email ?? old('email') }}" placeholder="Enter your email" required autofocus>
                            @error('email')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input id="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   name="password" placeholder="Enter new password" required autocomplete="new-password">
                            @error('password')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password-confirm" class="form-label">Confirm Password</label>
                            <input id="password-confirm" type="password"
                                   class="form-control"
                                   name="password_confirmation" placeholder="Confirm password" required autocomplete="new-password">
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success w-100">Reset Password</button>
                        </div>

                        <div class="text-center mt-3">
                            <a href="{{ route('login') }}">Remember your password? Login</a>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection