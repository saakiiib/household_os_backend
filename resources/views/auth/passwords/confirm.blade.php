@extends('auth.master')

@section('title', 'Confirm Password')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card mt-4">
            <div class="card-body p-4">

                <div class="text-center mt-2">
                    <h5 class="text-primary">Confirm Password</h5>
                    <p class="text-muted">Please confirm your password before continuing.</p>
                </div>

                <div class="p-2 mt-4">
                    <form method="POST" action="{{ route('password.confirm') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input id="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   name="password" placeholder="Enter your password" required autocomplete="current-password">
                            @error('password')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success w-100">Confirm Password</button>
                        </div>

                        @if (Route::has('password.request'))
                            <div class="text-center mt-3">
                                <a href="{{ route('password.request') }}">Forgot Your Password?</a>
                            </div>
                        @endif
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection