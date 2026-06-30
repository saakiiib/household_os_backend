@extends('admin.pages.master')
@section('title', 'Home Footer')
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
                        <h3 class="card-title mb-0 flex-grow-1">Home Footer</h3>
                    </div>

                    <form action="{{ route('admin.home-footer') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Home Footer <span class="text-danger">*</span></label>
                                        <textarea name="footer_content" class="form-control ckeditor-classic @error('footer_content') is-invalid @enderror"
                                            rows="4">{!! $companyDetails->footer_content !!}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-secondary">Update</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

@endsection
