@extends('admin.pages.master')
@section('title', 'Category')
@section('content')

    <div class="container-fluid" id="newBtnSection">
        <div class="row mb-3">
            <div class="col-auto">
                <button type="button" class="btn btn-primary" id="newBtn">
                    Add New Category
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid" id="addThisFormContainer">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1" id="cardTitle">Add New Category</h4>
                    </div>
                    <div class="card-body">
                        <form id="createThisForm">
                            @csrf
                            <input type="hidden" id="codeid" name="codeid">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Parent Category</label>
                                    <select class="form-control select2" id="parent_id" name="parent_id">
                                        <option value="">Select Parent Category</option>
                                        @foreach ($parentCategories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" placeholder=""></textarea>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Category Image</label>
                                    <input type="file" class="form-control" id="image" accept="image/*"
                                        onchange="previewImage(event, '#preview-image')">
                                    <img id="preview-image" src="#" alt="" class="img-thumbnail rounded mt-3"
                                        style="max-width: 300px; display: none;">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" id="addBtn" class="btn btn-primary">
                            Create
                        </button>
                        <button type="button" id="FormCloseBtn" class="btn btn-light">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid" id="contentContainer">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Categories</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoryTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Sl</th>
                                <th>Name</th>
                                <th>Parent Category</th>
                                <th>Image</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                    </table>
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
        
        // Function to load parent categories via AJAX
        function loadParentCategories() {
            $.ajax({
                url: "{{ route('parent.categories') }}",
                method: "GET",
                success: function(response) {
                    // Clear existing options except the first one
                    $('#parent_id').empty().append('<option value="">Select Parent Category</option>');

                    // Add new options
                    response.forEach(function(category) {
                        $('#parent_id').append('<option value="' + category.id + '">' + category.name +
                            '</option>');
                    });

                    // Trigger change to refresh Select2
                    $('#parent_id').trigger('change');
                },
                error: function(xhr) {
                    console.error('Failed to load parent categories');
                }
            });
        }

        // Initialize Select2 when document is ready
        $(document).ready(function() {

            $('#categoryTable').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                ajax: "{{ route('category.index') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'parent_category',
                        name: 'parent_category',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'image',
                        name: 'image',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $("#addThisFormContainer").hide();
            $("#newBtn").click(function() {
                clearform();
                $("#newBtn").hide(100);
                $("#addThisFormContainer").show(300);

                // Load fresh parent categories when opening the form
                loadParentCategories();

                // Re-initialize Select2 when form is shown
                $('#parent_id').select2({
                    placeholder: "Select Parent Category",
                    allowClear: true,
                    width: '100%'
                });
            });

            $("#FormCloseBtn").click(function() {
                $("#addThisFormContainer").hide(200);
                $("#newBtn").show(100);
                clearform();
            });

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            var url = "{{ URL::to('/admin/category') }}";
            var upurl = "{{ URL::to('/admin/category-update') }}";

            $("#addBtn").click(function() {
                //create
                if ($(this).val() == 'Create') {
                    var form_data = new FormData();
                    form_data.append("name", $("#name").val());
                    form_data.append("parent_id", $("#parent_id").val());
                    form_data.append("description", $("#description").val());

                    var featureImgInput = document.getElementById('image');
                    if (featureImgInput.files && featureImgInput.files[0]) {
                        form_data.append("image", featureImgInput.files[0]);
                    }

                    $.ajax({
                        url: url,
                        method: "POST",
                        contentType: false,
                        processData: false,
                        data: form_data,
                        success: function(d) {
                            showSuccess(d.message);
                            $("#addThisFormContainer").slideUp(300);
                            setTimeout(() => {
                                $("#newBtn").show(200);
                            }, 300);
                            reloadTable('#categoryTable');
                            clearform();

                            // Reload parent categories after successful creation
                            loadParentCategories();
                        },
                        error: function(xhr, status, error) {
                            if (xhr.status === 422) {
                                let firstError = Object.values(xhr.responseJSON.errors)[0][0];
                                showError(firstError);
                            } else {
                                showError(xhr.responseJSON?.message ?? "Something went wrong!");
                            }
                            console.error(xhr.responseText);
                        }
                    });
                }
                //create  end

                //Update
                if ($(this).val() == 'Update') {
                    var form_data = new FormData();
                    form_data.append("name", $("#name").val());
                    form_data.append("parent_id", $("#parent_id").val());
                    form_data.append("description", $("#description").val());

                    var featureImgInput = document.getElementById('image');
                    if (featureImgInput.files && featureImgInput.files[0]) {
                        form_data.append("image", featureImgInput.files[0]);
                    }

                    form_data.append("codeid", $("#codeid").val());

                    $.ajax({
                        url: upurl,
                        type: "POST",
                        dataType: 'json',
                        contentType: false,
                        processData: false,
                        data: form_data,
                        success: function(d) {
                            showSuccess(d.message);
                            $("#addThisFormContainer").hide();
                            $("#addThisFormContainer").slideUp(300);
                            setTimeout(() => {
                                $("#newBtn").show(200);
                            }, 300);
                            reloadTable('#categoryTable');
                            clearform();

                            // Reload parent categories after successful update
                            loadParentCategories();
                        },
                        error: function(xhr, status, error) {
                            if (xhr.status === 422) {
                                let firstError = Object.values(xhr.responseJSON.errors)[0][0];
                                showError(firstError);
                            } else {
                                showError(xhr.responseJSON?.message ?? "Something went wrong!");
                            }
                            console.error(xhr.responseText);
                        }
                    });
                }
                //Update  end
            });

            //Edit
            $("#contentContainer").on('click', '.editBtn', function() {
                $("#cardTitle").text('Update this data');
                codeid = $(this).attr('rid');
                info_url = url + '/' + codeid + '/edit';
                $.get(info_url, {}, function(d) {
                    populateForm(d);
                });
            });
            //Edit  end 

            function populateForm(data) {
                $("#name").val(data.name);
                $("#description").val(data.description);
                $("#codeid").val(data.id);
                $("#addBtn").val('Update');
                $("#addBtn").html('Update');
                $("#addThisFormContainer").show(300);
                $("#newBtn").hide(100);

                // Load fresh parent categories when editing
                loadParentCategories();

                // Set Select2 value for parent_id after a slight delay to ensure options are loaded
                setTimeout(function() {
                    if (data.parent_id) {
                        $('#parent_id').val(data.parent_id).trigger('change');
                    } else {
                        $('#parent_id').val(null).trigger('change');
                    }
                }, 300);

                var featureImagePreview = document.getElementById('preview-image');
                if (data.image) {
                    featureImagePreview.src = data.image;
                    featureImagePreview.style.display = 'block';
                } else {
                    featureImagePreview.src = "#";
                    featureImagePreview.style.display = 'none';
                }
            }

            function clearform() {
                $('#createThisForm')[0].reset();
                $("#addBtn").val('Create');
                $("#addBtn").html('Create');
                $('#preview-image').attr('src', '#');
                $('#preview-image').hide();
                $("#cardTitle").text('Add new Category');

                // Clear Select2
                $('#parent_id').val(null).trigger('change');
            }
        });
    </script>
@endsection