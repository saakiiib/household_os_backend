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
                ->addColumn('user_link', function ($p) {
                    if (!$p->user) return 'N/A';
                    return '<a href="' . route('admin.users.show', $p->user) . '" class="text-body">' . e($p->user->name) . '</a>';
                })
                ->addColumn('household_link', function ($p) {
                    if (!$p->household) return 'N/A';
                    return '<a href="' . route('admin.households.show', $p->household) . '" class="text-body">' . e($p->household->name) . '</a>';
                })
                ->addColumn('amount_fmt', fn($p) => '$' . number_format($p->amount, 2))
                ->addColumn('gateway_fmt', fn($p) => ucfirst($p->gateway))
                ->addColumn('status_badge', function ($p) {
                    $cls = match($p->status) { 'completed' => 'success', 'failed' => 'danger', default => 'warning' };
                    return '<span class="badge badge-soft-' . $cls . '">' . ucfirst($p->status) . '</span>';
                })
                ->addColumn('date_fmt', fn($p) => $p->created_at->format('d M Y'))
                ->rawColumns(['user_link', 'household_link', 'status_badge'])
                ->make(true);
        }

        return view('admin.pages.payments', [
            'payments' => Payment::with('user', 'household')->latest()->paginate(20),
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load('user', 'household', 'plan', 'subscription');

        return view('admin.pages.payment-show', compact('payment'));
    }
}
