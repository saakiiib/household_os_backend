@extends('admin.pages.master')
@section('title', 'Payments')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Payments</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="paymentsTable" class="table table-bordered table-striped align-middle" width="100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Household</th>
                                    <th>Amount</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$(function() {
    $('#paymentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("admin.payments.index") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'user_link', name: 'user_name', orderable: false, searchable: false },
            { data: 'household_link', name: 'household_name', orderable: false, searchable: false },
            { data: 'amount_fmt', name: 'amount', orderable: true },
            { data: 'gateway_fmt', name: 'gateway', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: true },
            { data: 'date_fmt', name: 'created_at', orderable: true },
        ]
    });
});
</script>
@endsection
