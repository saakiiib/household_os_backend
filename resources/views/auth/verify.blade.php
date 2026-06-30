@extends('auth.master')

@section('title', 'Verify Email')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
        <div class="card mt-4">
            <div class="card-body p-4">

                <div class="text-center mt-2">
                    <h5 class="text-primary">Verify Your Email</h5>
                    <p class="text-muted">Check your email for the verification link</p>
                </div>

                @if (session('resent'))
                    <div class="alert alert-success mt-3" role="alert">
                        A new verification link has been sent to your email address.
                    </div>
                @endif

                <div class="p-2 mt-4">
                    <p class="text-center mb-3">If you did not receive the email, you can request another below:</p>

                    <form method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success w-100">Resend Verification Email</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                           Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection