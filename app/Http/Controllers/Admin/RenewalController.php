<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Renewal;
use Yajra\DataTables\Facades\DataTables;

class RenewalController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return DataTables::of(Renewal::with('household', 'createdBy', 'vehicle')
                ->select('id', 'title', 'category', 'status', 'amount', 'due_date', 'household_id'))
                ->addColumn('title_link', function ($r) {
                    return '<a href="' . route('admin.renewals.show', $r) . '" class="fw-semibold text-body">' . e($r->title) . '</a>';
                })
                ->addColumn('household_link', function ($r) {
                    if (!$r->household) return 'N/A';
                    return '<a href="' . route('admin.households.show', $r->household) . '" class="text-body">' . e($r->household->name) . '</a>';
                })
                ->addColumn('category_fmt', fn($r) => ucfirst(str_replace('_', ' ', $r->category)))
                ->addColumn('amount_fmt', fn($r) => $r->amount ? '$' . number_format($r->amount, 2) : '-')
                ->addColumn('status_badge', function ($r) {
                    $cls = match($r->status) { 'completed' => 'success', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($r->status) . '</span>';
                })
                ->addColumn('due_date_fmt', fn($r) => $r->due_date ? $r->due_date->format('d M Y') : '-')
                ->rawColumns(['title_link', 'household_link', 'status_badge'])
                ->make(true);
        }

        return view('admin.pages.renewals');
    }

    public function show(Renewal $renewal)
    {
        $renewal->load('household', 'createdBy', 'vehicle', 'parent', 'children.createdBy');

        $siblings = Renewal::where('household_id', $renewal->household_id)
            ->where('id', '!=', $renewal->id)
            ->latest()
            ->take(10)
            ->get();

        return view('admin.pages.renewal-show', compact('renewal', 'siblings'));
    }
}
