@extends('frontend.master')
@section('title', 'About Us')

@section('content')
    <div class="container py-5">
        <div class="text-center mb-5">
            <h2 class="fw-bold">About ShopBD</h2>
            <p class="text-muted">Bangladesh's trusted ecommerce platform since 2018</p>
        </div>

        <div class="row g-3 mb-5">
            @foreach ($stats as $s)
                <div class="col-6 col-md-3">
                    <div class="text-center bg-white rounded-3 p-4 border">
                        <i class="{{ $s['icon'] }}" style="font-size:32px; color:#1a3c6e;"></i>
                        <div class="fw-bold fs-4 mt-2">{{ $s['value'] }}</div>
                        <div class="text-muted small">{{ $s['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row align-items-center mb-5 g-4">
            <div class="col-md-6">
                <h4 class="fw-bold">Our Story</h4>
                <p class="text-muted" style="line-height:1.8;">ShopBD was founded in 2018 with a simple mission — make
                    quality products accessible to everyone in Bangladesh. We started with a small team and a few hundred
                    products. Today, we serve over 12,000 happy customers across the country with a catalog of 5,000+
                    products.</p>
                <p class="text-muted" style="line-height:1.8;">We believe in fair pricing, fast delivery, and excellent
                    customer service. Every product on our platform is carefully vetted for quality.</p>
            </div>
            <div class="col-md-6">
                <img src="https://placehold.co/540x320/e8f4ff/1a3c6e?text=Our+Story" class="img-fluid rounded-3"
                    alt="story">
            </div>
        </div>

        <h4 class="fw-bold text-center mb-4">Meet the Team</h4>
        <div class="row g-3 justify-content-center">
            @foreach ($team as $member)
                <div class="col-6 col-md-3">
                    <div class="text-center bg-white rounded-3 p-4 border">
                        <img src="{{ $member['image'] }}" class="rounded-circle mb-3" style="width:80px; height:80px;"
                            alt="{{ $member['name'] }}">
                        <div class="fw-semibold">{{ $member['name'] }}</div>
                        <div class="text-muted small">{{ $member['role'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection