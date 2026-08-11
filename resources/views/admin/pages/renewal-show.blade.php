@extends('admin.pages.master')
@section('title', $renewal->title)
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">
                    <a href="{{ route('admin.renewals.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Renewals</a>
                    &nbsp;/&nbsp;{{ $renewal->title }}
                </h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Renewal Details</h5>
                    <div class="mt-3">
                        <p class="mb-2"><strong>Title:</strong> {{ $renewal->title }}</p>
                        <p class="mb-2"><strong>Category:</strong> {{ ucfirst(str_replace('_', ' ', $renewal->category)) }}</p>
                        <p class="mb-2"><strong>Household:</strong>
                            @if($renewal->household)
                                <a href="{{ route('admin.households.show', $renewal->household) }}">{{ $renewal->household->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Created By:</strong>
                            @if($renewal->createdBy)
                                <a href="{{ route('admin.users.show', $renewal->createdBy) }}">{{ $renewal->createdBy->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Amount:</strong> {{ $renewal->amount ? '$' . number_format($renewal->amount, 2) : '-' }}</p>
                        <p class="mb-2"><strong>Due Date:</strong> {{ $renewal->due_date ? $renewal->due_date->format('d M Y') : '-' }}</p>
                        <p class="mb-2"><strong>Status:</strong>
                            @php $cls = match($renewal->status) { 'completed' => 'success', default => 'warning' }; @endphp
                            <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($renewal->status) }}</span>
                        </p>
                        @if($renewal->vehicle)
                            <p class="mb-0"><strong>Vehicle:</strong> {{ $renewal->vehicle->make ?? '' }} {{ $renewal->vehicle->model ?? '' }} {{ $renewal->vehicle->year ?? '' }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if($renewal->parent)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Parent Renewal</h5>
                    <a href="{{ route('admin.renewals.show', $renewal->parent) }}" class="fw-semibold">{{ $renewal->parent->title }}</a>
                </div>
            </div>
            @endif
        </div>

        <div class="col-xl-8">
            @if($renewal->children->count())
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Renewal History ({{ $renewal->children->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Renewal</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($renewal->children as $child)
                                <tr>
                                    <td><a href="{{ route('admin.renewals.show', $child) }}" class="fw-semibold text-body">{{ $child->title }}</a></td>
                                    <td>{{ $child->amount ? '$' . number_format($child->amount, 2) : '-' }}</td>
                                    <td>
                                        @php $cls = match($child->status) { 'completed' => 'success', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($child->status) }}</span>
                                    </td>
                                    <td>{{ $child->due_date ? $child->due_date->format('d M Y') : '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($siblings->count())
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Other Renewals in {{ $renewal->household->name ?? 'Household' }}</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Renewal</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siblings as $sibling)
                                <tr>
                                    <td><a href="{{ route('admin.renewals.show', $sibling) }}" class="fw-semibold text-body">{{ $sibling->title }}</a></td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $sibling->category)) }}</td>
                                    <td>{{ $sibling->amount ? '$' . number_format($sibling->amount, 2) : '-' }}</td>
                                    <td>
                                        @php $cls = match($sibling->status) { 'completed' => 'success', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($sibling->status) }}</span>
                                    </td>
                                    <td>{{ $sibling->due_date ? $sibling->due_date->format('d M Y') : '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
