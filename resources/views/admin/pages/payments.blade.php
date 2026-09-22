@extends('admin.pages.master')
@section('title', 'Payments')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div><h4 class="mb-sm-0 font-size-18">Payments & Revenue</h4><small class="text-muted">{{ $rangeLabel }} · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</small></div>
                <div class="btn-group btn-group-sm"><a class="btn btn-outline-primary" href="?period=today">Today</a><a class="btn btn-outline-primary" href="?period=month">This Month</a><a class="btn btn-outline-primary" href="?period=last_month">Last Month</a></div>
            </div>
        </div>
    </div>

    <div class="card mb-3"><div class="card-body py-2"><form class="row g-2 align-items-end" method="get"><input type="hidden" name="period" value="custom"><div class="col-sm-4"><label class="form-label mb-1">Custom from</label><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm"></div><div class="col-sm-4"><label class="form-label mb-1">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm"></div><div class="col-sm-4"><button class="btn btn-sm btn-primary">Apply Custom Range</button></div></form></div></div>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Revenue</p>
                            <h4 class="mb-0">£{{ number_format($revenue, 2) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-money-pound-circle-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">New Paid Subscriptions</p>
                            <h4 class="mb-0">{{ number_format($newPaidSubscriptions) }}</h4>
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
                                    <th>Household</th>
                                    <th>Plan</th>
                                    <th>Monthly / Annual</th>
                                    <th>Amount</th>
                                    <th>Platform</th>
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
        ajax: { url: "{{ route('admin.payments.index') }}", data: { period: @json(request('period','month')), from: @json(request('from')), to: @json(request('to')) } },
        columns: [
            { data: 'household_link', name: 'household_id', orderable: false, searchable: false },
            { data: 'plan_fmt', name: 'plan', orderable: false, searchable: false },
            { data: 'billing_fmt', name: 'billing', orderable: false, searchable: false },
            { data: 'amount_fmt', name: 'amount', orderable: true, searchable: false },
            { data: 'gateway_fmt', name: 'gateway', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'date_fmt', name: 'created_at', orderable: true, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        language: { emptyTable: 'No records found', zeroRecords: 'No matching payments' }
    });
});
</script>
@endsection
