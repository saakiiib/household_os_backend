<nav class="site-nav">
    <a href="{{ route('frontend.home') }}" class="logo" @spa>Shop<span>BD</span></a>
    <ul class="nav-links">
        <li><a href="{{ route('frontend.home') }}" @spa
                class="{{ request()->routeIs('frontend.home') ? 'active' : '' }}">Home</a></li>
        <li><a href="{{ route('frontend.shop') }}" @spa
                class="{{ request()->routeIs('frontend.shop') ? 'active' : '' }}">Shop</a></li>
        <li><a href="{{ route('frontend.about') }}" @spa
                class="{{ request()->routeIs('frontend.about') ? 'active' : '' }}">About</a></li>
        <li><a href="{{ route('frontend.contact') }}" @spa
                class="{{ request()->routeIs('frontend.contact') ? 'active' : '' }}">Contact</a></li>
    </ul>
</nav>