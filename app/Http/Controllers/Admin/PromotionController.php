<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\Promotion; use Illuminate\Http\Request;
class PromotionController extends Controller {
 public function index(){ return view('admin.pages.promotions',['promotions'=>Promotion::latest()->get()]); }
 public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:120','code'=>'nullable|string|max:80|unique:promotions,code','apple_offer_identifier'=>'nullable|string|max:160','eligible_plans'=>'nullable|array','benefit'=>'nullable|string|max:255','eligibility'=>'required|in:all,free,trial,paid','usage_limit'=>'nullable|integer|min:1','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at']);$d['is_active']=$r->boolean('is_active');Promotion::create($d);return back()->with('success','Promotion created. Apple subscription benefits must be configured with the matching App Store offer identifier.');}
 public function toggle(Promotion $promotion){$promotion->update(['is_active'=>!$promotion->is_active]);return back();}
}
