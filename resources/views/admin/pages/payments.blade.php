@extends('admin.pages.master')
@section('title', 'Payments')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Payment Management</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total</p>
                            <h4 class="mb-0">{{ number_format($totalPayments) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-money-dollar-circle-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Succeeded</p>
                            <h4 class="mb-0">{{ number_format($succeededPayments) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-checkbox-circle-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Failed</p>
                            <h4 class="mb-0">{{ number_format($failedPayments) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-danger text-danger rounded fs-3"><i class="ri-close-circle-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Refunded</p>
                            <h4 class="mb-0">{{ number_format($refundedPayments) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-refund-2-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">All Payments</h4>
                    <span class="badge bg-soft-primary fs-12">{{ number_format($totalPayments) }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="payments-table" class="table table-hover table-centered align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Household</th>
                                    <th>Amount</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
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
$(function () {
    $('#payments-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.payments.index') }}",
        columns: [
            { data: 'user_link', name: 'user_id', orderable: false, searchable: false },
            { data: 'household_link', name: 'household_id', orderable: false, searchable: false },
            { data: 'amount_fmt', name: 'amount', orderable: true, searchable: false },
            { data: 'gateway_fmt', name: 'gateway', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'date_fmt', name: 'created_at', orderable: true, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[5, 'desc']],
        language: { emptyTable: 'No records found', zeroRecords: 'No matching payments' }
    });
});
</script>
@endsection
