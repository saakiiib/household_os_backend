@extends('admin.pages.master')
@section('title', 'Payments')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Payments</h4>
        </div>
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
            { data: 'user_name', name: 'user_name', orderable: false, searchable: false },
            { data: 'household_name', name: 'household_name', orderable: false, searchable: false },
            { data: 'amount_fmt', name: 'amount', orderable: false, searchable: false },
            { data: 'gateway_fmt', name: 'gateway', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'date_fmt', name: 'created_at', orderable: false, searchable: false },
        ]
    });
});
</script>
@endsection
