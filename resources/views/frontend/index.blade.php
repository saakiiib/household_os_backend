@extends('frontend.master')
@section('title', 'Home')

@section('style')
    <style>
        .hero-card {
            border-radius: 14px;
            overflow: hidden;
            min-height: 260px;
            position: relative;
            color: #fff;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .12);
        }

        .hero-card img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: .24;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding: 28px;
        }

        .category-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
        }

        .category-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
            color: #1a3c6e;
        }

        .product-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
        }

        .product-card img {
            width: 100%;
            height: 190px;
            object-fit: cover;
        }

        .product-card .body {
            padding: 14px;
        }

        .product-price {
            color: #1a3c6e;
            font-weight: 700;
            font-size: 17px;
        }

        .product-old-price {
            color: #adb5bd;
            text-decoration: line-through;
            font-size: 13px;
            margin-left: 6px;
        }

        .stars {
            color: #ffd166;
            font-size: 12px;
        }
    </style>
@endsection

@section('content')
    <div class="container py-5">
        <div class="row g-4 mb-4">
            @foreach ($banners as $banner)
                <div class="col-12 col-lg-6">
                    <div class="hero-card" style="background: {{ $banner['bg'] }};">
                        <img src="{{ $banner['image'] }}" alt="{{ $banner['title'] }}">
                        <div class="hero-content">
                            <h3 class="fw-bold mb-2">{{ $banner['title'] }}</h3>
                            <p class="mb-3" style="opacity:.9;">{{ $banner['subtitle'] }}</p>
                            <a href="{{ route('frontend.shop') }}" class="btn btn-light btn-sm px-3"
                                @spa>{{ $banner['btn'] }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Shop by Category</h4>
            <a href="{{ route('frontend.shop') }}" class="text-decoration-none" @spa>View all</a>
        </div>

        <div class="row g-3 mb-5">
            @foreach ($categories as $category)
                <div class="col-6 col-md-3">
                    <div class="category-card">
                        <div class="category-icon" style="background: {{ $category['color'] }};">
                            <i class="{{ $category['icon'] }}"></i>
                        </div>
                        <div class="fw-semibold">{{ $category['name'] }}</div>
                        <div class="text-muted small">{{ $category['count'] }} items</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Featured Products</h4>
            <a href="{{ route('frontend.shop') }}" class="text-decoration-none" @spa>Browse shop</a>
        </div>

        <div class="row g-3">
            @foreach ($featuredProducts as $product)
                <div class="col-6 col-lg-4">
                    <div class="product-card">
                        <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}">
                        <div class="body">
                            @if ($product['badge'])
                                <span
                                    class="badge bg-danger-subtle text-danger border border-danger-subtle mb-2">{{ $product['badge'] }}</span>
                            @endif
                            <div class="fw-semibold mb-1">{{ $product['name'] }}</div>
                            <div class="stars mb-2">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="{{ $i <= $product['rating'] ? 'ri-star-fill' : 'ri-star-line' }}"></i>
                                @endfor
                            </div>
                            <div class="mb-2">
                                <span class="product-price">Tk {{ number_format($product['price']) }}</span>
                                @if ($product['old_price'])
                                    <span class="product-old-price">Tk {{ number_format($product['old_price']) }}</span>
                                @endif
                            </div>
                            <a href="{{ route('frontend.shop') }}" class="btn btn-primary btn-sm w-100" @spa>Add to
                                Cart</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection