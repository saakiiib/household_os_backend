<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return DataTables::of(Payment::with('user', 'household', 'plan')
                ->select('id', 'user_id', 'household_id', 'amount', 'currency', 'gateway', 'status', 'created_at'))
                ->addColumn('user_name', fn($p) => $p->user->name ?? 'N/A')
                ->addColumn('household_name', fn($p) => $p->household->name ?? 'N/A')
                ->addColumn('amount_fmt', fn($p) => '$' . number_format($p->amount, 2))
                ->addColumn('gateway_fmt', fn($p) => ucfirst($p->gateway))
                ->addColumn('status_badge', function ($p) {
                    $cls = match($p->status) { 'completed' => 'success', 'failed' => 'danger', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($p->status) . '</span>';
                })
                ->addColumn('date_fmt', fn($p) => $p->created_at->format('d M Y'))
                ->rawColumns(['status_badge'])
                ->make(true);
        }

        return view('admin.pages.payments');
    }
}
