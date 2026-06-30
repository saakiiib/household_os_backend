@extends('auth.master')

@section('title', 'Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card mt-4">
            <div class="card-body p-4">

                <div class="text-center mt-2">
                    <h5 class="text-primary">Welcome Back!</h5>
                    <p class="text-muted">Sign in to continue</p>
                </div>

                <div class="p-2 mt-4">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   name="email" value="{{ old('email') }}" placeholder="Enter your email" required autofocus>
                            @error('email')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="float-end">
                                <a href="{{ route('password.request') }}" class="text-muted">Forgot password?</a>
                            </div>
                            <label for="password" class="form-label">Password</label>
                            <div class="position-relative auth-pass-inputgroup mb-3">
                                <input id="password" type="password"
                                       class="form-control pe-5 @error('password') is-invalid @enderror"
                                       name="password" placeholder="Enter password" required>
                                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" type="button" id="password-addon">
                                    <i class="ri-eye-fill align-middle"></i>
                                </button>
                                @error('password')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success w-100">Sign In</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <div class="mt-4 text-center">
            <p class="mb-0">Don't have an account?
                <a href="{{ route('register') }}" class="fw-semibold text-primary text-decoration-underline">Sign Up</a>
            </p>
        </div>
    </div>
</div>
@endsection