@extends('admin.pages.master')
@section('title', 'Company SEO')
@section('content')

    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-xl-12">

                @if (session()->has('success'))
                    <div class="alert alert-success pt-3 mb-3" id="successMessage">{{ session()->get('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0 flex-grow-1">SEO Meta Fields</h3>
                    </div>

                    <form action="{{ route('admin.company.seo-meta.update') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Google Site Verification</label>
                                        <input type="text"
                                            class="form-control @error('google_site_verification') is-invalid @enderror"
                                            name="google_site_verification"
                                            value="{{ old('google_site_verification', $companyDetails->google_site_verification ?? '') }}"
                                            placeholder="Enter google site verification">
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Meta Title</label>
                                        <input type="text" class="form-control @error('meta_title') is-invalid @enderror"
                                            name="meta_title"
                                            value="{{ old('meta_title', $companyDetails->meta_title ?? '') }}"
                                            placeholder="Enter meta title">
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Meta Description</label>
                                        <textarea class="form-control @error('meta_description') is-invalid @enderror" name="meta_description" rows="3"
                                            placeholder="Enter meta description">{{ old('meta_description', $companyDetails->meta_description ?? '') }}</textarea>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Meta Keywords (comma separated)</label>
                                        <input type="text"
                                            class="form-control @error('meta_keywords') is-invalid @enderror"
                                            name="meta_keywords"
                                            value="{{ old('meta_keywords', $companyDetails->meta_keywords ?? '') }}"
                                            placeholder="e.g. product, item, shop">
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Meta Image (1200x630 recommended)</label>
                                        <input type="file"
                                            class="form-control-file @error('meta_image') is-invalid @enderror"
                                            id="meta_image" name="meta_image" accept="image/*"
                                            onchange="previewImage(event, '#preview-meta-image')">
                                        <img class="img-thumbnail mt-2"
                                            src="{{ isset($companyDetails->meta_image) ? asset('uploads/company/meta/' . $companyDetails->meta_image) : '' }}"
                                            alt="Current Meta Image" id="preview-meta-image">
                                        @error('meta_image')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-secondary" value="Update">Update</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

@endsection
