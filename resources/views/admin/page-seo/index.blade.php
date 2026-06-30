@extends('admin.pages.master')
@section('title', 'Page SEO')

@section('content')

<div class="container-fluid" id="addThisFormContainer" style="display:none;">
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header"><h4 id="cardTitle">Update Page SEO</h4></div>
                <div class="card-body">
                    <form id="createThisForm" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" id="codeid" name="id">
                        <div class="mb-3">
                            <label>Page</label>
                            <input type="text" class="form-control" id="page_label" disabled>
                        </div>
                        <div class="mb-3">
                            <label>Meta Title</label>
                            <input type="text" class="form-control" id="meta_title" name="meta_title">
                        </div>
                        <div class="mb-3">
                            <label>Meta Description</label>
                            <textarea class="form-control" id="meta_description" name="meta_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Meta Keywords</label>
                            <input type="text" class="form-control" id="meta_keywords" name="meta_keywords">
                        </div>
                        <div class="mb-3">
                            <label>Meta Image <small class="text-muted">1200x630</small></label>
                            <input type="file" class="form-control" id="meta_image" name="meta_image" accept="image/*">
                            <img id="image_preview" src="#" class="img-thumbnail mt-2" style="display:none;max-height:120px;">
                        </div>
                        <div class="text-end">
                            <button type="button" id="addBtn" class="btn btn-primary" value="Update">Update</button>
                            <button type="button" id="FormCloseBtn" class="btn btn-light">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="card">
        <div class="card-header"><h4>Page SEO</h4></div>
        <div class="card-body">
            <table id="pageSeoTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Sl</th>
                        <th>Page</th>
                        <th>Meta Title</th>
                        <th>Meta Image</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
$(function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const pageSeoTable = $('#pageSeoTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('page-seo.index') }}",
        columns: [
            { data: 'DT_RowIndex',     orderable: false, searchable: false },
            { data: 'page_label',      name: 'page_label' },
            { data: 'meta_title',      name: 'meta_title' },
            { data: 'image',           name: 'image',      orderable: false, searchable: false },
            { data: 'action',          name: 'action',     orderable: false, searchable: false }
        ]
    });

    $('#meta_image').on('change', function () {
        if (this.files[0]) $('#image_preview').attr('src', URL.createObjectURL(this.files[0])).show();
    });

    function resetForm() {
        $('#createThisForm')[0].reset();
        $('#codeid').val('');
        $('#image_preview').hide();
    }

    $('#FormCloseBtn').click(function () {
        $('#addThisFormContainer').hide(200);
        resetForm();
    });

    $('#addBtn').click(function () {
        const fd = new FormData(document.getElementById('createThisForm'));
        $.ajax({
            url: "{{ route('page-seo.update') }}",
            type: 'POST', data: fd, contentType: false, processData: false,
            success: function (res) {
                showSuccess(res.message);
                $('#addThisFormContainer').hide();
                pageSeoTable.ajax.reload(null, false);
                resetForm();
            },
            error: function (xhr) {
                showError(xhr.status === 422
                    ? Object.values(xhr.responseJSON.errors)[0][0]
                    : (xhr.responseJSON?.message ?? 'Something went wrong'));
            }
        });
    });

    $(document).on('click', '.EditBtn', function () {
        $.get("{{ url('/admin/page-seo') }}/" + $(this).data('id') + "/edit", function (res) {
            resetForm();
            $('#codeid').val(res.id);
            $('#page_label').val(res.page_label);
            $('#meta_title').val(res.meta_title);
            $('#meta_description').val(res.meta_description);
            $('#meta_keywords').val(res.meta_keywords);
            $('#image_preview').attr('src', res.meta_image ? '/' + res.meta_image : '#').toggle(!!res.meta_image);
            $('#addThisFormContainer').show(300);
        });
    });
});
</script>
@endsection