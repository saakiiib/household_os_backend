@extends('auth.master')

@section('title', 'Register')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card mt-4">
            <div class="card-body p-4">

                <div class="text-center mt-2">
                    <h5 class="text-primary">Create Your Account</h5>
                    <p class="text-muted">Register to get started</p>
                </div>

                <div class="p-2 mt-4">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input id="name" type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name') }}" placeholder="Enter your full name" required autofocus>
                            @error('name')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   name="email" value="{{ old('email') }}" placeholder="Enter your email" required>
                            @error('email')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input id="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   name="password" placeholder="Enter password" required>
                            @error('password')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password-confirm" class="form-label">Confirm Password</label>
                            <input id="password-confirm" type="password"
                                   class="form-control" name="password_confirmation"
                                   placeholder="Confirm password" required>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success w-100">Register</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <div class="mt-4 text-center">
            <p class="mb-0">Already have an account?
                <a href="{{ route('login') }}" class="fw-semibold text-primary text-decoration-underline">Login</a>
            </p>
        </div>
    </div>
</div>
@endsection