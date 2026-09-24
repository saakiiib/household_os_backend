<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller
{
    private function range(Request $request): array
    {
        $period = $request->get('period', 'month');
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay(), 'Today'],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), 'Last Month'],
            'custom' => [Carbon::parse($request->get('from', now()->startOfMonth()->toDateString()))->startOfDay(), Carbon::parse($request->get('to', now()->toDateString()))->endOfDay(), 'Custom'],
            default => [now()->startOfMonth(), now()->endOfMonth(), 'This Month'],
        };
    }

    public function index(Request $request)
    {
        [$from,$to,$rangeLabel] = $this->range($request);
        if ($request->ajax()) {
            $query = Payment::with('user','household','plan','subscription')->whereBetween('created_at',[$from,$to])
                ->select('id','user_id','household_id','subscription_id','subscription_plan_id','amount','currency','gateway','status','created_at');
            return DataTables::of($query)
                ->addColumn('household_link', fn($p) => $p->household ? '<a href="'.route('admin.households.show',$p->household).'" class="text-body">'.e($p->household->name).'</a>' : 'N/A')
                ->addColumn('plan_fmt', fn($p) => e($p->plan->name ?? $p->plan->code ?? '—'))
                ->addColumn('billing_fmt', fn($p) => e(ucfirst($p->subscription->billing_period ?? '—')))
                ->addColumn('amount_fmt', fn($p) => '£'.number_format($p->amount,2))
                ->addColumn('gateway_fmt', fn($p) => ucfirst($p->gateway))
                ->addColumn('status_badge', function($p){$state=$p->subscription->status ?? $p->status;$cls=match($state){'succeeded','completed','active'=>'success','failed'=>'danger','refunded'=>'info','grace_period','billing_retry'=>'warning','expired'=>'secondary',default=>'warning'};return '<span class="badge badge-soft-'.$cls.'">'.e(ucfirst(str_replace('_',' ',$state))).'</span>';})
                ->addColumn('date_fmt', fn($p) => $p->created_at->copy()->timezone('Europe/London')->format('d M Y H:i'))
                ->addColumn('action', fn($p) => '<a href="'.route('admin.payments.show',$p).'" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>')
                ->rawColumns(['household_link','status_badge','action'])->make(true);
        }
        $base=Payment::whereBetween('created_at',[$from,$to]);
        $revenue=(clone $base)->whereIn('status',['succeeded','completed'])->sum('amount');
        $succeededPayments=(clone $base)->whereIn('status',['succeeded','completed'])->count();
        $failedPayments=(clone $base)->where('status','failed')->count();
        $refundedPayments=(clone $base)->where('status','refunded')->count();
        $newPaidSubscriptions=Subscription::whereBetween('created_at',[$from,$to])->whereIn('status',['active','grace_period','billing_retry'])->count();
        $totalPayments=(clone $base)->count();
        return view('admin.pages.payments',compact('totalPayments','succeededPayments','failedPayments','refundedPayments','revenue','newPaidSubscriptions','rangeLabel','from','to'));
    }
    public function show(Payment $payment){$payment->load('user','household','plan','subscription');return view('admin.pages.payment-show',compact('payment'));}
}
