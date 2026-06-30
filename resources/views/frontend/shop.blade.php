@extends('frontend.master')
@section('title', 'Shop')

@section('style')
    <style>
        .product-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            transition: all .25s;
            border: 1px solid #e9ecef;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, .1);
        }

        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: contain;
            padding: 12px;
            background: #f8f9fa;
        }

        .product-card .card-body {
            padding: 16px;
        }

        .price {
            font-size: 17px;
            font-weight: 700;
            color: #1a3c6e;
        }

        .old-price {
            font-size: 13px;
            color: #adb5bd;
            text-decoration: line-through;
            margin-left: 6px;
        }

        .stars {
            color: #ffd166;
            font-size: 12px;
        }

        .filter-bar {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            position: sticky;
            top: 80px;
        }

        .filter-bar .form-check-label {
            font-size: 13px;
            text-transform: capitalize;
        }

        #searchInput {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 8px 14px;
            font-size: 14px;
            width: 100%;
        }

        #searchInput:focus {
            outline: none;
            border-color: #1a3c6e;
            box-shadow: 0 0 0 3px rgba(26, 60, 110, .1);
        }

        .pagination-wrap {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 32px;
            flex-wrap: wrap;
        }

        .pagination-wrap button {
            border: 1px solid #dee2e6;
            background: #fff;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 14px;
            cursor: pointer;
            transition: all .2s;
        }

        .pagination-wrap button:hover {
            background: #e8f4ff;
            border-color: #1a3c6e;
            color: #1a3c6e;
        }

        .pagination-wrap button.active {
            background: #1a3c6e;
            color: #fff;
            border-color: #1a3c6e;
        }

        .pagination-wrap button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }
    </style>
@endsection

@section('content')
    <div @class(['container', 'py-5'])>

        <div @class(['d-flex', 'flex-wrap', 'justify-content-between', 'align-items-center', 'gap-3', 'mb-4'])>
            <div>
                <h3 @class(['fw-bold', 'mb-0'])>All Products</h3>
                <p @class(['text-muted', 'mb-0'])>{{ $total }} product{{ $total !== 1 ? 's' : '' }} found</p>
            </div>
            <div style="min-width:280px;">
                <input type="text" id="searchInput" placeholder="Search products..." value="{{ $search }}"
                    autocomplete="off">
            </div>
        </div>

        <div @class(['row', 'g-4'])>

            {{-- Sidebar --}}
            <div @class(['col-md-3'])>
                <div @class(['filter-bar'])>
                    <h6 @class(['fw-bold', 'mb-3'])>Category</h6>
                    <div @class(['form-check', 'mb-2'])>
                        <input @class(['form-check-input', 'cat-filter']) type="radio" name="cat" id="cat-all"
                            value="" {{ $category === '' ? 'checked' : '' }}>
                        <label @class(['form-check-label']) for="cat-all">All</label>
                    </div>
                    @foreach ($categories as $i => $cat)
                        <div @class(['form-check', 'mb-2'])>
                            <input @class(['form-check-input', 'cat-filter']) type="radio" name="cat"
                                value="{{ $cat }}" id="cat-{{ $i }}"
                                {{ $category === $cat ? 'checked' : '' }}>
                            <label @class(['form-check-label']) for="cat-{{ $i }}">{{ $cat }}</label>
                        </div>
                    @endforeach

                    <hr>
                    <h6 @class(['fw-bold', 'mb-3'])>Sort by</h6>
                    <select @class(['form-select', 'form-select-sm']) id="sortSelect">
                        <option value="" {{ $sort === '' ? 'selected' : '' }}>Default</option>
                        <option value="low" {{ $sort === 'low' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="high" {{ $sort === 'high' ? 'selected' : '' }}>Price: High to Low</option>
                        <option value="rating"{{ $sort === 'rating' ? 'selected' : '' }}>Top Rated</option>
                        <option value="az" {{ $sort === 'az' ? 'selected' : '' }}>Name: A to Z</option>
                    </select>

                    <hr>
                    <h6 @class(['fw-bold', 'mb-2'])>Price Range</h6>
                    <div @class(['d-flex', 'gap-2', 'align-items-center'])>
                        <input type="number" id="priceMin" @class(['form-control', 'form-control-sm']) placeholder="Min"
                            value="{{ $minPrice }}" min="0">
                        <span>—</span>
                        <input type="number" id="priceMax" @class(['form-control', 'form-control-sm']) placeholder="Max"
                            value="{{ $maxPrice }}" min="0">
                    </div>
                    <button @class(['btn', 'btn-sm', 'btn-outline-primary', 'w-100', 'mt-2']) id="applyPrice">Apply</button>

                    <hr>
                    <h6 @class(['fw-bold', 'mb-2'])>Per Page</h6>
                    <select @class(['form-select', 'form-select-sm']) id="perPageSelect">
                        @foreach ([3, 6, 9, 12] as $n)
                            <option value="{{ $n }}" {{ $perPage == $n ? 'selected' : '' }}>
                                {{ $n }} per page</option>
                        @endforeach
                    </select>

                    <button @class(['btn', 'btn-sm', 'btn-link', 'w-100', 'mt-3', 'text-muted']) id="resetFilters">Reset all filters</button>
                </div>
            </div>

            {{-- Products --}}
            <div @class(['col-md-9'])>
                @if (count($products) > 0)
                    <div @class(['row', 'g-3'])>
                        @foreach ($products as $p)
                            <div @class(['col-6', 'col-lg-4'])>
                                <div @class(['product-card'])>
                                    <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" loading="lazy">
                                    <div @class(['card-body'])>
                                        <div @class(['badge', 'bg-secondary', 'mb-1'])
                                            style="font-size:10px; text-transform:capitalize;">{{ $p['category'] }}</div>
                                        <div @class(['fw-semibold', 'mb-1'])
                                            style="font-size:13px; line-height:1.4; height:38px; overflow:hidden;">
                                            {{ $p['name'] }}</div>
                                        <div @class(['stars', 'mb-1'])>
                                            @for ($i = 1; $i <= 5; $i++)
                                                <i @class([
                                                    'ri-star-fill' => $i <= $p['rating'],
                                                    'ri-star-line' => $i > $p['rating'],
                                                ])></i>
                                            @endfor
                                        </div>
                                        <div @class(['mb-2'])>
                                            <span @class(['price'])>৳{{ number_format($p['price']) }}</span>
                                            @if ($p['old_price'])
                                                <span @class(['old-price'])>৳{{ number_format($p['old_price']) }}</span>
                                            @endif
                                        </div>
                                        <button @class(['btn', 'btn-primary', 'btn-sm', 'w-100', 'add-to-cart-btn'])
                                            data-id="{{ $p['id'] }}" data-name="{{ $p['name'] }}"
                                            data-price="{{ $p['price'] }}">
                                            <i @class(['ri-shopping-cart-line'])></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div @class(['text-center', 'py-5', 'text-muted'])>
                        <i @class(['ri-search-line']) style="font-size:48px; display:block; margin-bottom:12px;"></i>
                        No products found. Try a different search or filter.
                    </div>
                @endif

                {{-- Pagination --}}
                @if ($totalPages > 1)
                    <div @class(['pagination-wrap'])>
                        <button {{ $currentPage === 1 ? 'disabled' : '' }} onclick="goPage({{ $currentPage - 1 }})">
                            <i @class(['ri-arrow-left-s-line'])></i> Prev
                        </button>
                        @for ($i = 1; $i <= $totalPages; $i++)
                            <button
                                @class([
                                    'active' => $i === $currentPage,
                                ])
                                onclick="goPage({{ $i }})">
                                {{ $i }}
                            </button>
                        @endfor
                        <button {{ $currentPage === $totalPages ? 'disabled' : '' }}
                            onclick="goPage({{ $currentPage + 1 }})">
                            Next <i @class(['ri-arrow-right-s-line'])></i>
                        </button>
                    </div>
                @endif
            </div>

        </div>
    </div>
@endsection

@section('script')
    <script>
        function buildUrl(overrides) {
            var params = {
                search: $('#searchInput').val().trim(),
                category: $('input[name="cat"]:checked').val(),
                sort: $('#sortSelect').val(),
                min_price: $('#priceMin').val().trim(),
                max_price: $('#priceMax').val().trim(),
                per_page: $('#perPageSelect').val(),
                page: '1',
            };
            $.extend(params, overrides || {});
            $.each(params, function(k, v) {
                if (v === '' || v === null || v === undefined) delete params[k];
            });
            var query = $.param(params);
            return '{{ route('frontend.shop') }}' + (query ? '?' + query : '');
        }

        function goPage(page) {
            window.spaNavigate(buildUrl({
                page: page
            }), {
                push: true,
                scroll: true
            });
        }

        var searchTimer;
        $('#searchInput').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                window.spaNavigate(buildUrl({
                    page: 1
                }), {
                    push: true,
                    scroll: false
                });
            }, 400);
        });

        $('.cat-filter').on('change', function() {
            window.spaNavigate(buildUrl({
                page: 1
            }), {
                push: true,
                scroll: false
            });
        });

        $('#sortSelect').on('change', function() {
            window.spaNavigate(buildUrl({
                page: 1
            }), {
                push: true,
                scroll: false
            });
        });

        $('#perPageSelect').on('change', function() {
            window.spaNavigate(buildUrl({
                page: 1
            }), {
                push: true,
                scroll: false
            });
        });

        $('#applyPrice').on('click', function() {
            window.spaNavigate(buildUrl({
                page: 1
            }), {
                push: true,
                scroll: false
            });
        });

        $('#resetFilters').on('click', function() {
            window.spaNavigate('{{ route('frontend.shop') }}', {
                push: true,
                scroll: true
            });
        });
    </script>
@endsection