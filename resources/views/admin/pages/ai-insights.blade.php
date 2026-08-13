@extends('admin.pages.master')
@section('title', 'AI Insights')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0 font-size-18">AI Insights</h4>
                        <p class="text-muted mb-0">Model activity, risk detection and automated intelligence signals.</p>
                    </div>
                    <div class="page-title-right d-flex gap-2 align-items-center">
                        <span class="badge bg-soft-success text-success fs-12"><i class="ri-record-circle-line"></i> Live</span>
                        <button class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Download report</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            @foreach ($kpis as $k)
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
                                <span class="badge {{ $k['trend'] >= 0 ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                    <i class="ri-arrow-{{ $k['trend'] >= 0 ? 'up' : 'down' }}-line"></i> {{ abs($k['trend']) }}%
                                </span>
                                <span class="ms-1">vs last period</span>
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div>
                            <h4 class="card-title mb-0">Trend — Last 30 days</h4>
                            <p class="text-muted mb-0 fs-13">Daily activity across the platform</p>
                        </div>
                        <div class="ms-auto">
                            <span class="badge bg-soft-primary text-primary fs-12">Last 30 days</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="trend-chart" style="height:320px"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0">Priority findings</h4>
                        <span class="badge bg-soft-danger text-danger ms-auto">{{ count($findings) }} alerts</span>
                    </div>
                    <div class="card-body">
                        <div class="vstack gap-3">
                            @forelse ($findings as $f)
                                @php
                                    $levelClass = $f['level'] === 'danger' ? 'bg-soft-danger text-danger' : ($f['level'] === 'warn' ? 'bg-soft-warning text-warning' : 'bg-soft-primary text-primary');
                                    $dotClass = $f['level'] === 'danger' ? 'bg-danger' : ($f['level'] === 'warn' ? 'bg-warning' : 'bg-primary');
                                @endphp
                                <div class="d-flex align-items-start border-bottom pb-3">
                                    <span class="rounded-circle {{ $dotClass }} me-3 mt-1" style="width:10px;height:10px;"></span>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1 fw-semibold">{{ $f['title'] }}</h6>
                                            <span class="badge {{ $levelClass }} fs-12 text-uppercase">{{ $f['level'] }}</span>
                                        </div>
                                        <p class="text-muted mb-1 fs-13">{{ $f['detail'] }}</p>
                                        <small class="text-muted"><i class="ri-time-line"></i> {{ $f['time'] }}</small>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-muted mb-0">No findings reported</p>
                            @endforelse
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
    var trend = new ApexCharts(document.querySelector("#trend-chart"), {
        chart: { type: 'area', height: 320, toolbar: { show: false }, zoom: { enabled: false } },
        series: [{ name: 'Activity', data: @json($trendSeries) }],
        xaxis: { categories: @json($trendLabels) },
        colors: ['#405189'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        grid: { borderColor: '#f1f1f1' },
        legend: { position: 'top' }
    });
    trend.render();
});
</script>
@endsection
