@extends('admin.pages.master')
@section('title', 'Executive Dashboard')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0 font-size-18">Executive Dashboard</h4>
                        <p class="text-muted mb-0">A real-time view of platform growth, revenue, engagement, operations and risk.</p>
                    </div>
                    <div class="page-title-right d-flex gap-2 align-items-center">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            @php
                $kpis = [
                    ['label' => 'Total Households', 'value' => number_format($totalHouseholds), 'icon' => 'ri-home-line', 'key' => 'households'],
                    ['label' => 'Total Users', 'value' => number_format($totalUsers), 'icon' => 'ri-user-line', 'key' => 'users'],
                    ['label' => 'Active Subscriptions', 'value' => number_format($activeSubscriptions), 'icon' => 'ri-vip-crown-line', 'key' => 'subscriptions'],
                    ['label' => 'Monthly Revenue', 'value' => '£' . number_format($monthlyRevenue, 2), 'icon' => 'ri-money-pound-circle-line', 'key' => 'revenue'],
                    ['label' => 'Documents Stored', 'value' => number_format($totalDocuments), 'icon' => 'ri-folder-line', 'key' => 'documents'],
                    ['label' => 'Tasks Today', 'value' => number_format($tasksToday), 'icon' => 'ri-task-line', 'key' => 'tasks'],
                    ['label' => 'Renewals Due', 'value' => number_format($renewalsDue), 'icon' => 'ri-refresh-line', 'key' => 'renewals'],
                ];
            @endphp
            @foreach ($kpis as $k)
                @php
                    $t = $trend[$k['key']];
                @endphp
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-2 text-truncate">{{ $k['label'] }}</p>
                                    <h4 class="mb-0">{{ $k['value'] }}</h4>
                                </div>
                                <div class="avatar-sm">
                                    <span class="avatar-title bg-soft-primary text-primary rounded fs-3">
                                        <i class="{{ $k['icon'] }}"></i>
                                    </span>
                                </div>
                            </div>
                            <p class="mt-3 mb-0 text-muted fs-13">
                                <span class="badge {{ $t >= 0 ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                    <i class="ri-arrow-{{ $t >= 0 ? 'up' : 'down' }}-line"></i> {{ abs($t) }}%
                                </span>
                                <span class="ms-1">vs last period</span>
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div>
                            <h4 class="card-title mb-0">User & Household Growth</h4>
                            <p class="text-muted mb-0 fs-13">Daily, weekly and monthly platform growth</p>
                        </div>
                        <div class="ms-auto">
                            <span class="badge bg-soft-secondary text-secondary fs-12">Last 12 months</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="growth-chart" style="height:320px"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div>
                            <h4 class="card-title mb-0">Revenue</h4>
                            <p class="text-muted mb-0 fs-13">MRR, upgrades and trial conversions</p>
                        </div>
                        <div class="ms-auto">
                            <span class="badge bg-soft-success text-success fs-12"><i class="ri-arrow-up-line"></i> {{ abs($trend['revenue']) }}%</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="revenue-chart" style="height:320px"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div>
                            <h4 class="card-title mb-0">Recent Activities</h4>
                            <p class="text-muted mb-0 fs-13">Live platform activity and security events</p>
                        </div>
                        <div class="ms-auto">
                            <a href="{{ route('admin.page', ['page' => 'audit-logs']) }}" class="btn btn-soft-primary btn-sm">View all</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Event</th>
                                        <th>Subject</th>
                                        <th>Actor</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentActivities as $a)
                                        <tr>
                                            <td class="fw-medium">{{ $a->description ?? '—' }}</td>
                                            <td>{{ class_basename($a->subject_type ?? '') }} #{{ $a->subject_id ?? '' }}</td>
                                            <td>{{ $a->user->name ?? 'System' }}</td>
                                            <td class="text-muted">{{ $a->created_at->diffForHumans() }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No activity recorded yet</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0">Platform Health</h4>
                        <span class="badge bg-soft-success text-success ms-auto">Operational</span>
                    </div>
                    <div class="card-body">
                        <div class="vstack gap-3">
                            @foreach ($health as $h)
                                @php
                                    $ok = ($h['status'] ?? 'ok') === 'ok';
                                @endphp
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                                    <span class="text-muted">{{ $h['label'] }}</span>
                                    <span class="text-{{ $ok ? 'success' : 'warning' }} fw-medium">
                                        <i class="ri-{{ $ok ? 'checkbox-circle' : 'error-warning' }}-line"></i> {{ $h['value'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var growth = new ApexCharts(document.querySelector("#growth-chart"), {
        chart: { type: 'area', height: 320, toolbar: { show: false }, zoom: { enabled: false } },
        series: [
            { name: 'Users', data: @json($growthUsers) },
            { name: 'Households', data: @json($growthHouseholds) }
        ],
        xaxis: { categories: @json($growthLabels) },
        colors: ['#405189', '#0ab39c'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        grid: { borderColor: '#f1f1f1' },
        legend: { position: 'top' }
    });
    growth.render();

    var revenue = new ApexCharts(document.querySelector("#revenue-chart"), {
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        series: [{ name: 'Revenue', data: @json($revenueSeries) }],
        xaxis: { categories: @json($revenueLabels) },
        colors: ['#405189'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%', distributed: false } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#f1f1f1' },
        yaxis: { labels: { formatter: function (val) { return '£' + Math.round(val); } } }
    });
    revenue.render();
});
</script>
@endsection
