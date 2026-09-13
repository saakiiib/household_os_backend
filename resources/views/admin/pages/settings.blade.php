@extends('admin.pages.master')
@section('title', 'Platform Settings')

@section('content')
<div class="container-fluid">

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ri-check-line me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Platform Settings</h4>
                    <p class="text-muted mb-0">Manage your platform branding, URLs, and configuration.</p>
                </div>
                <div class="page-title-right">
                    <button type="submit" form="settingsForm" class="btn btn-primary btn-sm"><i class="ri-save-line"></i> Save All Settings</button>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" id="settingsForm">
        @csrf

        {{-- BRANDING --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-palette-line me-2"></i>Branding</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Company / App Name</label>
                                <input type="text" class="form-control" name="company_name" value="{{ $settings['company_name'] ?? 'Household OS' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Support Email</label>
                                <input type="email" class="form-control" name="support_email" value="{{ $settings['support_email'] ?? '' }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Company Address</label>
                                <input type="text" class="form-control" name="company_address" value="{{ $settings['company_address'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- LOGO & BRANDING FILES --}}
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-image-line me-2"></i>Logo</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            @if(!empty($settings['logo']))
                                <img src="{{ asset($settings['logo']) }}" alt="Logo" id="logoPreview" class="img-fluid rounded" style="max-height: 80px;">
                            @else
                                <img src="{{ asset('logo.png') }}" alt="Logo" id="logoPreview" class="img-fluid rounded" style="max-height: 80px;">
                            @endif
                        </div>
                        <input type="file" class="form-control" name="logo" id="logoInput" accept="image/*">
                        <div class="form-text">Used in emails, admin sidebar, and app. Recommended: 500x120px PNG with transparent background.</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-global-line me-2"></i>Favicon</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            @if(!empty($settings['favicon']))
                                <img src="{{ asset($settings['favicon']) }}" alt="Favicon" id="faviconPreview" class="img-fluid rounded" style="max-height: 64px;">
                            @else
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; margin: 0 auto;">
                                    <i class="ri-global-line fs-2 text-muted"></i>
                                </div>
                            @endif
                        </div>
                        <input type="file" class="form-control" name="favicon" id="faviconInput" accept="image/*">
                        <div class="form-text">Browser tab icon. Recommended: 32x32px ICO or PNG.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CONTACT INFO --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-phone-line me-2"></i>Contact Information</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Phone Number 1</label>
                                <input type="text" class="form-control" name="phone_1" value="{{ $settings['phone_1'] ?? '' }}" placeholder="+44 123 456 789">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone Number 2</label>
                                <input type="text" class="form-control" name="phone_2" value="{{ $settings['phone_2'] ?? '' }}" placeholder="+44 987 654 321">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Website URL</label>
                                <input type="url" class="form-control" name="website_url" value="{{ $settings['website_url'] ?? '' }}" placeholder="https://householdosapp.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- APP STORE URLs --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-smartphone-line me-2"></i>App Store Links</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Apple App Store URL</label>
                                <input type="url" class="form-control" name="app_store_url" value="{{ $settings['app_store_url'] ?? '' }}" placeholder="https://apps.apple.com/app/household-os/id...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Google Play Store URL</label>
                                <input type="url" class="form-control" name="play_store_url" value="{{ $settings['play_store_url'] ?? '' }}" placeholder="https://play.google.com/store/apps/details?id=...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- API URLS --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-server-line me-2"></i>API Endpoints</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Sandbox API URL</label>
                                <input type="url" class="form-control" name="sandbox_api_url" value="{{ $settings['sandbox_api_url'] ?? '' }}" placeholder="https://sandbox-api.householdosapp.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Live API URL</label>
                                <input type="url" class="form-control" name="live_api_url" value="{{ $settings['live_api_url'] ?? '' }}" placeholder="https://api.householdosapp.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SOCIAL LINKS --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-share-line me-2"></i>Social Media Links</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Twitter / X</label>
                                <input type="url" class="form-control" name="social_twitter" value="{{ $settings['social_twitter'] ?? '' }}" placeholder="https://x.com/householdos">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Facebook</label>
                                <input type="url" class="form-control" name="social_facebook" value="{{ $settings['social_facebook'] ?? '' }}" placeholder="https://facebook.com/householdos">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Instagram</label>
                                <input type="url" class="form-control" name="social_instagram" value="{{ $settings['social_instagram'] ?? '' }}" placeholder="https://instagram.com/householdos">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0"><i class="ri-footer-line me-2"></i>Email & Footer</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Email From Address</label>
                                <input type="email" class="form-control" name="mail_from_address" value="{{ $settings['mail_from_address'] ?? '' }}" placeholder="app@householdosapp.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email From Name</label>
                                <input type="text" class="form-control" name="mail_from_name" value="{{ $settings['mail_from_name'] ?? 'Household OS' }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Footer Tagline</label>
                                <input type="text" class="form-control" name="footer_tagline" value="{{ $settings['footer_tagline'] ?? 'The operating system for modern family life.' }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Footer Disclaimer</label>
                                <input type="text" class="form-control" name="footer_disclaimer" value="{{ $settings['footer_disclaimer'] ?? 'This is an automated service email. Please do not share verification or reset codes with anyone.' }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Save All Settings</button>
            </div>
        </div>
    </form>

</div>
@endsection

@section('script')
<script>
document.getElementById('logoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('logoPreview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
});
document.getElementById('faviconInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('faviconPreview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
});
</script>
@endsection
