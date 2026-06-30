function showSuccess(msg) {
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: msg ?? 'Success!',
            showConfirmButton: false,
            timer: 1000
        });
    }, 300);
}

function showError(msg) {
    setTimeout(() => {
        Swal.fire({
            icon: 'error',
            title: msg ?? 'Something went wrong!',
            showConfirmButton: false,
            timer: 2000
        });
    }, 300);
}

function reload(ms = 2000) {
    setTimeout(() => location.reload(), ms);
}

function pageTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function previewImage(event, imgSelector) {
    if (event.target.files && event.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            $(imgSelector).attr('src', e.target.result).show();
        };
        reader.readAsDataURL(event.target.files[0]);
    }
}

function reloadTable(tableSelector = null) {
    let table = tableSelector
        ? $(tableSelector).DataTable()
        : $('table.dataTable:visible').DataTable();

    if (table) table.ajax.reload(null, false);
}

function initUI(context = document) {

    $(context).find('.select2').each(function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({ width: '100%' });
        }
    });

    $(context).find('.summernote').each(function () {

        if ($(this).next('.note-editor').length) return;

        $(this).summernote({
            height: 300,

            toolbar: [
                ['style', ['style', 'bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['font', ['fontname', 'fontsize', 'color', 'forecolor', 'backcolor', 'superscript', 'subscript']],
                ['para', ['ul', 'ol', 'paragraph', 'height']],
                ['insert', ['link', 'picture', 'video', 'table', 'hr']],
                ['view', ['fullscreen', 'codeview', 'help']],
                ['misc', ['undo', 'redo']]
            ],

            popover: {
                image: [
                    ['image', ['resizeFull', 'resizeHalf', 'resizeQuarter', 'resizeNone']],
                    ['float', ['floatLeft', 'floatRight', 'floatNone']],
                    ['remove', ['removeMedia']]
                ],
                link: [
                    ['link', ['linkDialogShow', 'unlink']]
                ],
                table: [
                    ['add', ['addRowDown', 'addRowUp', 'addColLeft', 'addColRight']],
                    ['delete', ['deleteRow', 'deleteCol', 'deleteTable']]
                ]
            },

            dialogsInBody: true,
            disableDragAndDrop: false,
            shortcuts: true
        });
    });
}

let deleteUrl = '';
let tableSelector = null;
let deleteMethod = 'DELETE';

$(document).on('click', '.deleteBtn', function () {
    deleteUrl = $(this).data('delete-url');
    tableSelector = $(this).data('table') || null;
    deleteMethod = $(this).data('method') || 'DELETE';
    $('#confirmDeleteModal').modal('show');
});

$('#confirmDeleteBtn').on('click', function () {

    if (!deleteUrl) return;

    $.ajax({
        url: deleteUrl,
        method: deleteMethod,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (res) {
            showSuccess(res.message ?? 'Deleted successfully!');
            $('#confirmDeleteModal').modal('hide');
            reloadTable(tableSelector);
        },
        error: function (xhr) {
            showError(xhr.responseJSON?.message);
            $('#confirmDeleteModal').modal('hide');
        }
    });
});

$(document).on('change', '.toggle-switch', function () {

    let el = $(this);

    $.ajax({
        url: el.data('url'),
        method: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            id: el.data('id'),
            status: el.prop('checked') ? 1 : 0,
        },

        success: function (res) {
            showSuccess(res.message ?? 'Updated');
            reloadTable(el.data('table'));
        },

        error: function (xhr) {
            el.prop('checked', !el.prop('checked'));
            showError(xhr.responseJSON?.message);
        }
    });
});

window.showLoader = function () {
    Swal.fire({
        title: 'Loading',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
};

window.hideLoader = function () {
    Swal.close();
};

$(document).on('click', '.loader-btn', function () {
    showLoader();
});

$(document).ajaxComplete(function () {
    hideLoader();
});

$(document).on('click', '.editBtn', function () {
    pageTop();
});

$(document).on('submit', '.spa-form', function (e) {
    e.preventDefault();

    var form     = $(this);
    var url      = form.attr('action');
    var formData = new FormData(this);

    form.find('.summernote').each(function () {
        var name = $(this).attr('name');
        if (name) {
            formData.set(name, $(this).summernote('code'));
        }
    });

    $.ajax({
        url:         url,
        method:      'POST',
        data:        formData,
        contentType: false,
        processData: false,
        success: function (res) {
            if (res.success) {
                showSuccess(res.message ?? 'Saved successfully!');
            }
        },
        error: function (xhr) {
            if (xhr.status === 422) {
                var first = Object.values(xhr.responseJSON.errors)[0][0];
                showError(first);
            } else {
                showError(xhr.responseJSON?.message ?? 'Something went wrong.');
            }
        }
    });
});