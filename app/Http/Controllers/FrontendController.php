<?php

namespace App\Http\Controllers;

use App\Models\CompanyDetails;
use App\Models\PageSeo;
use Illuminate\Http\Request;
use OpenGraph;
use SEOMeta;
use Twitter;

class FrontendController extends Controller
{

    public function index()
    {
        $featuredProducts = [
            ['id' => 1, 'name' => 'Wireless Headphones', 'price' => 2499, 'old_price' => 3200, 'badge' => 'Sale', 'rating' => 4, 'image' => 'https://placehold.co/300x300/e8f4ff/3d5a99?text=Headphones'],
            ['id' => 2, 'name' => 'Smart Watch Pro',     'price' => 5999, 'old_price' => null,  'badge' => 'New',  'rating' => 5, 'image' => 'https://placehold.co/300x300/fff4e8/996a3d?text=Watch'],
            ['id' => 3, 'name' => 'Leather Backpack',    'price' => 1899, 'old_price' => 2500,  'badge' => 'Sale', 'rating' => 4, 'image' => 'https://placehold.co/300x300/f0ffe8/3d9950?text=Bag'],
            ['id' => 4, 'name' => 'Running Shoes',       'price' => 3200, 'old_price' => null,  'badge' => null,   'rating' => 5, 'image' => 'https://placehold.co/300x300/ffe8f4/993d6a?text=Shoes'],
            ['id' => 5, 'name' => 'Sunglasses UV400',    'price' => 899,  'old_price' => 1200,  'badge' => 'Sale', 'rating' => 3, 'image' => 'https://placehold.co/300x300/f4e8ff/6a3d99?text=Glasses'],
            ['id' => 6, 'name' => 'Portable Speaker',   'price' => 1599, 'old_price' => null,  'badge' => 'Hot',  'rating' => 4, 'image' => 'https://placehold.co/300x300/e8fff4/3d9980?text=Speaker'],
        ];

        $categories = [
            ['name' => 'Electronics', 'icon' => 'ri-cpu-line',        'count' => 120, 'color' => '#e8f4ff'],
            ['name' => 'Fashion',     'icon' => 'ri-t-shirt-line',     'count' => 85,  'color' => '#fff4e8'],
            ['name' => 'Sports',      'icon' => 'ri-basketball-line',  'count' => 64,  'color' => '#f0ffe8'],
            ['name' => 'Books',       'icon' => 'ri-book-open-line',   'count' => 210, 'color' => '#ffe8f4'],
        ];

        $banners = [
            ['title' => 'Summer Sale Up to 50% Off', 'subtitle' => 'Shop the latest trends', 'btn' => 'Shop Now', 'bg' => '#1a3c6e', 'image' => 'https://placehold.co/600x300/1a3c6e/ffffff?text=Summer+Sale'],
            ['title' => 'New Arrivals This Week',    'subtitle' => 'Fresh picks just for you',  'btn' => 'Explore',   'bg' => '#2d6e3e', 'image' => 'https://placehold.co/600x300/2d6e3e/ffffff?text=New+Arrivals'],
        ];

        $this->seo();

        return spa('frontend.index', compact('featuredProducts', 'categories', 'banners'));
    }

    public function shop(Request $request)
    {
        $response = file_get_contents('https://fakestoreapi.com/products');
        $raw = json_decode($response, true);

        $allProducts = [];
        $multiplier = 10;
        foreach (range(1, $multiplier) as $round) {
            foreach ($raw as $p) {
                $allProducts[] = [
                    'id'        => $p['id'] + (($round - 1) * 20),
                    'name'      => $p['title'],
                    'price'     => (float) $p['price'],
                    'old_price' => $p['price'] > 20 ? round($p['price'] * 1.3, 2) : null,
                    'category'  => $p['category'],
                    'rating'    => round($p['rating']['rate']),
                    'image'     => $p['image'],
                ];
            }
        }

        $categories = array_values(array_unique(array_column($allProducts, 'category')));

        $search = $request->input('search', '');
        if ($search) {
            $allProducts = array_values(array_filter(
                $allProducts,
                fn($p) =>
                str_contains(strtolower($p['name']), strtolower($search)) ||
                    str_contains(strtolower($p['category']), strtolower($search))
            ));
        }

        $category = $request->input('category', '');
        if ($category) {
            $allProducts = array_values(array_filter($allProducts, fn($p) => $p['category'] === $category));
        }

        $minPrice = $request->input('min_price', '');
        $maxPrice = $request->input('max_price', '');
        if ($minPrice !== '') {
            $allProducts = array_values(array_filter($allProducts, fn($p) => $p['price'] >= (float)$minPrice));
        }
        if ($maxPrice !== '') {
            $allProducts = array_values(array_filter($allProducts, fn($p) => $p['price'] <= (float)$maxPrice));
        }

        $sort = $request->input('sort', '');
        usort($allProducts, function ($a, $b) use ($sort) {
            if ($sort === 'low')    return $a['price'] <=> $b['price'];
            if ($sort === 'high')   return $b['price'] <=> $a['price'];
            if ($sort === 'rating') return $b['rating'] <=> $a['rating'];
            if ($sort === 'az')     return strcmp($a['name'], $b['name']);
            return $a['id'] <=> $b['id'];
        });

        $perPage     = in_array((int)$request->input('per_page', 6), [3, 6, 9, 12]) ? (int)$request->input('per_page', 6) : 6;
        $total       = count($allProducts);
        $totalPages  = (int) ceil($total / $perPage) ?: 1;
        $currentPage = max(1, min((int)$request->input('page', 1), $totalPages));
        $products    = array_slice($allProducts, ($currentPage - 1) * $perPage, $perPage);

        $this->seo('shop');

        return spa('frontend.shop', compact(
            'products',
            'categories',
            'total',
            'totalPages',
            'currentPage',
            'perPage',
            'search',
            'category',
            'sort',
            'minPrice',
            'maxPrice'
        ));
    }

    public function about()
    {
        $team = [
            ['name' => 'Sarah Ahmed',  'role' => 'CEO & Founder',    'image' => 'https://placehold.co/200x200/e8f4ff/3d5a99?text=SA'],
            ['name' => 'Rahim Uddin',  'role' => 'Head of Products',  'image' => 'https://placehold.co/200x200/f0ffe8/3d9950?text=RU'],
            ['name' => 'Nadia Islam',  'role' => 'Lead Designer',     'image' => 'https://placehold.co/200x200/ffe8f4/993d6a?text=NI'],
            ['name' => 'Karim Hassan', 'role' => 'Tech Lead',         'image' => 'https://placehold.co/200x200/fff4e8/996a3d?text=KH'],
        ];

        $stats = [
            ['label' => 'Happy Customers', 'value' => '12,000+', 'icon' => 'ri-user-smile-line'],
            ['label' => 'Products Listed', 'value' => '5,000+',  'icon' => 'ri-store-2-line'],
            ['label' => 'Orders Delivered', 'value' => '80,000+', 'icon' => 'ri-truck-line'],
            ['label' => 'Years in Business', 'value' => '6+',     'icon' => 'ri-award-line'],
        ];

        $this->seo('about');

        return spa('frontend.about', compact('team', 'stats'));
    }

    public function contact()
    {
        $this->seo('contact');

        return spa('frontend.contact');
    }

    private function seo($pageKey = null, $title = null, $description = null, $keywords = null, $image = null)
    {
        $company = CompanyDetails::first();
        $pageSeo = $pageKey ? PageSeo::where('page_key', $pageKey)->first() : null;

        $title = $title ?: ($pageSeo?->meta_title ?: $company?->meta_title);
        $description = $description ?: ($pageSeo?->meta_description ?: $company?->meta_description);
        $keywords = $keywords ?: ($pageSeo?->meta_keywords ?: $company?->meta_keywords);
        $image = $image ?: ($pageSeo?->meta_image
            ? asset($pageSeo->meta_image)
            : ($company?->meta_image
                ? asset('uploads/company/' . $company->meta_image)
                : null));

        if ($title) {
            SEOMeta::setTitle($title);
            OpenGraph::setTitle($title);
            Twitter::setTitle($title);
        }

        if ($description) {
            SEOMeta::setDescription($description);
            OpenGraph::setDescription($description);
            Twitter::setDescription($description);
        }

        if ($keywords) {
            SEOMeta::setKeywords($keywords);
        }

        if ($image) {
            OpenGraph::addImage($image);
            Twitter::setImage($image);
        }
    }
}