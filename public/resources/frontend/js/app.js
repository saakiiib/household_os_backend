$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

function showSuccess(msg) {
    $('#flash-msg').html('<div class="alert alert-success alert-dismissible">' + msg +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    setTimeout(function () {
        $('#flash-msg .alert').fadeOut(400, function () {
            $(this).remove();
        });
    }, 3500);
}

function showError(msg) {
    $('#flash-msg').html('<div class="alert alert-danger alert-dismissible">' + msg +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    setTimeout(function () {
        $('#flash-msg .alert').fadeOut(400, function () {
            $(this).remove();
        });
    }, 4000);
}