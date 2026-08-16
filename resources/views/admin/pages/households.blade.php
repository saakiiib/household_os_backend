@extends('admin.pages.master')
@section('title', 'Households')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Household Management</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Households</p>
                            <h4 class="mb-0">{{ number_format($totalHouseholds) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-home-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Total Members</p>
                            <h4 class="mb-0">{{ number_format($totalMembers) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-group-line"></i></span>
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
                            <h4 class="mb-0">{{ number_format($activeHouseholds) }}</h4>
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
                            <p class="text-muted mb-2 text-truncate">Archived</p>
                            <h4 class="mb-0">{{ number_format($archivedHouseholds) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-archive-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">All Households</h4>
                    <span class="badge bg-soft-primary fs-12">{{ number_format($totalHouseholds) }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="households-table" class="table table-hover table-centered align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Household</th>
                                    <th>Owner</th>
                                    <th>Members</th>
                                    <th>Status</th>
                                    <th>Created</th>
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
    $('#households-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.households.index') }}",
        columns: [
            { data: 'name_link', name: 'name', orderable: true, searchable: true },
            { data: 'creator_name', name: 'creator_id', orderable: false, searchable: false },
            { data: 'members_count', name: 'members_count', orderable: false, searchable: false },
            { data: 'subscription_status', name: 'status', orderable: false, searchable: false },
            { data: 'date_fmt', name: 'created_at', orderable: true, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[4, 'desc']],
        language: { emptyTable: 'No records found', zeroRecords: 'No matching households' }
    });
});
</script>
@endsection
