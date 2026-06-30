@extends('auth.master')

@section('title', 'Reset Password')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card mt-4">
            <div class="card-body p-4">

                <div class="text-center mt-2">
                    <h5 class="text-primary">Reset Password</h5>
                    <p class="text-muted">Enter your email address to receive a password reset link.</p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success mt-3" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="p-2 mt-4">
                    <form method="POST" action="{{ route('password.email') }}">
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

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success w-100">Send Password Reset Link</button>
                        </div>

                        <div class="text-center mt-3">
                            <a href="{{ route('login') }}">Remembered your password? Login</a>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection