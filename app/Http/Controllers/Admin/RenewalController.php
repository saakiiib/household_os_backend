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
                ->addColumn('household_name', fn($r) => $r->household->name ?? 'N/A')
                ->addColumn('category_fmt', fn($r) => ucfirst(str_replace('_', ' ', $r->category)))
                ->addColumn('amount_fmt', fn($r) => $r->amount ? '$' . number_format($r->amount, 2) : '-')
                ->addColumn('status_badge', function ($r) {
                    $cls = match($r->status) { 'completed' => 'success', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($r->status) . '</span>';
                })
                ->addColumn('due_date_fmt', fn($r) => $r->due_date ? $r->due_date->format('d M Y') : '-')
                ->rawColumns(['status_badge'])
                ->make(true);
        }

        return view('admin.pages.renewals');
    }
}
