<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h6>ShopBD</h6>
                <p style="font-size:14px; line-height:1.7;">Your trusted online shopping destination in Bangladesh.
                    Quality products, fast delivery.</p>
            </div>
            <div class="col-md-2">
                <h6>Shop</h6>
                <a href="{{ route('frontend.shop') }}" @spa>All Products</a>
                <a href="#">New Arrivals</a>
                <a href="#">Best Sellers</a>
                <a href="#">Sale</a>
            </div>
            <div class="col-md-2">
                <h6>Company</h6>
                <a href="{{ route('frontend.about') }}" @spa>About Us</a>
                <a href="{{ route('frontend.contact') }}" @spa>Contact</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms</a>
            </div>
            <div class="col-md-4">
                <h6>Contact</h6>
                <p style="font-size:14px;"><i class="ri-map-pin-line me-2"></i>Dhaka, Bangladesh</p>
                <p style="font-size:14px;"><i class="ri-phone-line me-2"></i>+880 1700-000000</p>
                <p style="font-size:14px;"><i class="ri-mail-line me-2"></i>hello@shopbd.com</p>
            </div>
        </div>
        <div class="footer-bottom">&copy; {{ date('Y') }} ShopBD. All rights reserved.</div>
    </div>
</footer>