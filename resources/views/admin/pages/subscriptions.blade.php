@extends('admin.pages.master')
@section('title', 'Subscriptions')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Subscription Management</h4>
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
                            <h4 class="mb-0">{{ number_format($totalSubscriptions) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-star-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Active</p>
                            <h4 class="mb-0">{{ number_format($activeSubscriptions) }}</h4>
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
                            <p class="text-muted mb-2 text-truncate">Trials</p>
                            <h4 class="mb-0">{{ number_format($trialSubscriptions) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-rocket-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Expired</p>
                            <h4 class="mb-0">{{ number_format($expiredSubscriptions) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-time-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">All Subscriptions</h4>
                    <span class="badge bg-soft-primary fs-12">{{ number_format($totalSubscriptions) }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="subscriptions-table" class="table table-hover table-centered align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Household</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Period End</th>
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
    $('#subscriptions-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.subscriptions.index') }}",
        columns: [
            { data: 'user_link', name: 'user_id', orderable: false, searchable: false },
            { data: 'household_link', name: 'household_id', orderable: false, searchable: false },
            { data: 'plan_name', name: 'subscription_plan_id', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'period_end_fmt', name: 'current_period_end', orderable: true, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[4, 'desc']],
        language: { emptyTable: 'No records found', zeroRecords: 'No matching subscriptions' }
    });
});
</script>
@endsection
