@extends('admin.pages.master')
@section('title', 'Company Details')
@section('content')

    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0 flex-grow-1">Company Details</h3>
                    </div>

                    <div class="card-body">
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#basic-info" role="tab">
                                    <i class="ri-building-line me-1"></i> Basic Info
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#contact-social" role="tab">
                                    <i class="ri-contacts-line me-1"></i> Contact & Social
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#content" role="tab">
                                    <i class="ri-file-text-line me-1"></i> Content
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#policies" role="tab">
                                    <i class="ri-shield-check-line me-1"></i> Policies
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#financial" role="tab">
                                    <i class="ri-bank-line me-1"></i> Financial
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#seo" role="tab">
                                    <i class="ri-search-line me-1"></i> SEO & Analytics
                                </a>
                            </li>
                        </ul>

                        <form class="spa-form" action="{{ route('companyDetails.update') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf

                            <!-- Tab panes -->
                            <div class="tab-content p-3 text-muted">
                                
                                <!-- Basic Info Tab -->
                                <div class="tab-pane active" id="basic-info" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Company Name <span class="text-danger">*</span></label>
                                                <input type="text"
                                                    class="form-control @error('company_name') is-invalid @enderror"
                                                    name="company_name" value="{{ $data->company_name }}" required>
                                                @error('company_name')
                                                    <span class="invalid-feedback">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Business Name</label>
                                                <input type="text"
                                                    class="form-control @error('business_name') is-invalid @enderror"
                                                    name="business_name" value="{{ $data->business_name }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Opening Time</label>
                                                <input type="text"
                                                    class="form-control @error('opening_time') is-invalid @enderror"
                                                    name="opening_time" value="{{ $data->opening_time }}"
                                                    placeholder="e.g., Mon-Fri: 9AM-6PM">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Currency</label>
                                                <select class="form-control" name="currency">
                                                    <option value="">Please choose currency</option>
                                                    <option value="$" {{ $data->currency == '$' ? 'selected' : '' }}>$ (USD)</option>
                                                    <option value="£" {{ $data->currency == '£' ? 'selected' : '' }}>£ (GBP)</option>
                                                    <option value="€" {{ $data->currency == '€' ? 'selected' : '' }}>€ (EUR)</option>
                                                    <option value="৳" {{ $data->currency == '৳' ? 'selected' : '' }}>৳ (BDT)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>Fav Icon</label>
                                                <input type="file" class="form-control @error('fav_icon') is-invalid @enderror"
                                                    name="fav_icon" onchange="previewImage(event, '#fav_icon_preview')"
                                                    accept="image/*">
                                            </div>
                                            <img class="img-thumbnail mt-2" id="fav_icon_preview" style="max-height: 100px;"
                                                src="{{ $data->fav_icon ? asset('uploads/company/' . $data->fav_icon) : '' }}"
                                                alt="">
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>Company Logo</label>
                                                <input type="file"
                                                    class="form-control @error('company_logo') is-invalid @enderror"
                                                    name="company_logo" onchange="previewImage(event, '#company_logo_preview')"
                                                    accept="image/*">
                                            </div>
                                            <img class="img-thumbnail mt-2" id="company_logo_preview" style="max-height: 100px;"
                                                src="{{ $data->company_logo ? asset('uploads/company/' . $data->company_logo) : '' }}"
                                                alt="">
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>Footer Logo</label>
                                                <input type="file"
                                                    class="form-control @error('footer_logo') is-invalid @enderror"
                                                    name="footer_logo" onchange="previewImage(event, '#footer_logo_preview')"
                                                    accept="image/*">
                                            </div>
                                            <img class="img-thumbnail mt-2" id="footer_logo_preview" style="max-height: 100px;"
                                                src="{{ $data->footer_logo ? asset('uploads/company/' . $data->footer_logo) : '' }}"
                                                alt="">
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact & Social Tab -->
                                <div class="tab-pane" id="contact-social" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Email (1)</label>
                                                <input type="email" class="form-control" name="email1"
                                                    value="{{ $data->email1 }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Email (2)</label>
                                                <input type="email" class="form-control" name="email2"
                                                    value="{{ $data->email2 }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label>Phone (1)</label>
                                                <input type="text" class="form-control" name="phone1"
                                                    value="{{ $data->phone1 }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label>Phone (2)</label>
                                                <input type="text" class="form-control" name="phone2"
                                                    value="{{ $data->phone2 }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label>Phone (3)</label>
                                                <input type="text" class="form-control" name="phone3"
                                                    value="{{ $data->phone3 }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label>Phone (4)</label>
                                                <input type="text" class="form-control" name="phone4"
                                                    value="{{ $data->phone4 }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>WhatsApp</label>
                                                <input type="text" class="form-control" name="whatsapp"
                                                    value="{{ $data->whatsapp }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Website</label>
                                                <input type="url" class="form-control" name="website"
                                                    value="{{ $data->website }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Address (1)</label>
                                                <input type="text" class="form-control" name="address1"
                                                    value="{{ $data->address1 }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Address (2)</label>
                                                <input type="text" class="form-control" name="address2"
                                                    value="{{ $data->address2 }}">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <h5 class="mb-3 mt-3">Social Media</h5>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>Facebook</label>
                                                <input type="url" class="form-control" name="facebook"
                                                    value="{{ $data->facebook }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>Instagram</label>
                                                <input type="url" class="form-control" name="instagram"
                                                    value="{{ $data->instagram }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>Twitter</label>
                                                <input type="url" class="form-control" name="twitter"
                                                    value="{{ $data->twitter }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>LinkedIn</label>
                                                <input type="url" class="form-control" name="linkedin"
                                                    value="{{ $data->linkedin }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>YouTube</label>
                                                <input type="url" class="form-control" name="youtube"
                                                    value="{{ $data->youtube }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-4">
                                            <div class="form-group">
                                                <label>TikTok</label>
                                                <input type="url" class="form-control" name="tiktok"
                                                    value="{{ $data->tiktok }}">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <h5 class="mb-3 mt-3">App Links</h5>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>App Store Link</label>
                                                <input type="url" class="form-control" name="appstore_link"
                                                    value="{{ $data->appstore_link }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Google Play Store Link</label>
                                                <input type="url" class="form-control" name="google_play_link"
                                                    value="{{ $data->google_play_link }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Tawk.to Widget</label>
                                                <input type="text" class="form-control" name="tawkto"
                                                    value="{{ $data->tawkto }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-12">
                                            <div class="form-group">
                                                <label>Google Map Embed Code</label>
                                                <textarea name="google_map" class="form-control" rows="3">{{ $data->google_map }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Content Tab -->
                                <div class="tab-pane" id="content" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>About Us</label>
                                                <textarea name="about_us" class="form-control summernote">{{ $data->about_us }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Header Content</label>
                                                <textarea name="header_content" class="form-control summernote">{{ $data->header_content }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Footer Content</label>
                                                <textarea name="footer_content" class="form-control summernote">{{ $data->footer_content }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Copyright Text</label>
                                                <textarea name="copyright" class="form-control" rows="2">{{ $data->copyright }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Policies Tab -->
                                <div class="tab-pane" id="policies" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Privacy Policy</label>
                                                <textarea name="privacy_policy" class="form-control summernote">{{ $data->privacy_policy }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Terms and Conditions</label>
                                                <textarea name="terms_and_conditions" class="form-control summernote">{{ $data->terms_and_conditions }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Refund Policy</label>
                                                <textarea name="refund_policy" class="form-control summernote">{{ $data->refund_policy }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Financial Tab -->
                                <div class="tab-pane" id="financial" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Company Registration Number</label>
                                                <input type="text" class="form-control" name="company_reg_number"
                                                    value="{{ $data->company_reg_number }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>VAT Number</label>
                                                <input type="text" class="form-control" name="vat_number"
                                                    value="{{ $data->vat_number }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>VAT Percent (%)</label>
                                                <input type="number" class="form-control" name="vat_percent"
                                                    value="{{ $data->vat_percent }}" step="0.01">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Bank Name</label>
                                                <input type="text" class="form-control" name="bank"
                                                    value="{{ $data->bank }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Account Number</label>
                                                <input type="text" class="form-control" name="account_number"
                                                    value="{{ $data->account_number }}">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Sort Code</label>
                                                <input type="text" class="form-control" name="sort_code"
                                                    value="{{ $data->sort_code }}">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Bank Information</label>
                                                <textarea name="bank_info" class="form-control summernote">{{ $data->bank_info }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SEO & Analytics Tab -->
                                <div class="tab-pane" id="seo" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Google Site Verification</label>
                                                <input type="text" class="form-control" name="google_site_verification"
                                                    value="{{ $data->google_site_verification }}"
                                                    placeholder="Enter verification code">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Google Analytics ID</label>
                                                <input type="text" class="form-control" name="google_analytics"
                                                    value="{{ $data->google_analytics }}"
                                                    placeholder="G-XXXXXXXXXX or UA-XXXXXXXXX-X">
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Google Tag Manager</label>
                                                <input type="text" class="form-control" name="google_tag_manager"
                                                    value="{{ $data->google_tag_manager }}"
                                                    placeholder="GTM-XXXXXXX">
                                            </div>
                                        </div>

                                                                                
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Facebook Pixel</label>
                                                <input type="text" class="form-control" name="facebook_pixel_id"
                                                    value="{{ $data->facebook_pixel_id }}"
                                                    placeholder="FB_PIXEL_ID">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <h5 class="mb-3 mt-3">Meta Tags</h5>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Meta Title</label>
                                                <input type="text" class="form-control" name="meta_title"
                                                    value="{{ $data->meta_title }}" maxlength="60">
                                                <small class="text-muted">Recommended: 50-60 characters</small>
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Meta OG Title (Open Graph)</label>
                                                <input type="text" class="form-control" name="meta_og_title"
                                                    value="{{ $data->meta_og_title }}">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Meta Description</label>
                                                <textarea name="meta_description" class="form-control" rows="3"
                                                    maxlength="160">{{ $data->meta_description }}</textarea>
                                                <small class="text-muted">Recommended: 150-160 characters</small>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Meta Keywords</label>
                                                <textarea name="meta_keywords" class="form-control" rows="2">{{ $data->meta_keywords }}</textarea>
                                                <small class="text-muted">Comma-separated keywords</small>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Meta OG Image (Open Graph Image)</label>
                                                <input type="file" class="form-control" name="meta_og_image"
                                                    onchange="previewImage(event, '#meta_og_image_preview')" accept="image/*">
                                                <small class="text-muted">Recommended: 1200x630px</small>
                                            </div>
                                            @if($data->meta_og_image)
                                                <img class="img-thumbnail mt-2" id="meta_og_image_preview" style="max-height: 150px;"
                                                    src="{{ asset('uploads/company/meta/' . $data->meta_og_image) }}" alt="">
                                            @else
                                                <img class="img-thumbnail mt-2 d-none" id="meta_og_image_preview" style="max-height: 150px;" alt="">
                                            @endif
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-save-line me-1"></i> Update Company Details
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            initUI();
        });
    </script>
@endsection