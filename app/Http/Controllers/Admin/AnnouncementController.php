<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\AdminAnnouncement; use App\Models\User; use App\Services\NotificationService; use Illuminate\Http\Request;
class AnnouncementController extends Controller {
 public function index(){return view('admin.pages.announcements',['announcements'=>AdminAnnouncement::latest()->take(100)->get()]);}
 public function store(Request $r){$d=$r->validate(['title'=>'required|string|max:160','message'=>'required|string|max:1500','audience'=>'required|in:all,free,trial,paid','action'=>'required|in:draft,send,schedule','scheduled_at'=>'nullable|required_if:action,schedule|date|after:now']);$a=AdminAnnouncement::create(['title'=>$d['title'],'message'=>$d['message'],'audience'=>$d['audience'],'status'=>$d['action']==='draft'?'draft':($d['action']==='schedule'?'scheduled':'sending'),'scheduled_at'=>$d['scheduled_at']??null,'created_by'=>auth()->id()]);if($d['action']==='send')$this->deliver($a);return back()->with('success','Announcement saved.');}
 public function deliver(AdminAnnouncement $a){$ids=$this->audienceIds($a->audience); if($ids){app(NotificationService::class)->sendToUsers($ids,$a->title,$a->message,'admin_announcement',['announcement_id'=>(string)$a->id]);}$a->update(['status'=>'sent','sent_at'=>now(),'recipient_count'=>count($ids)]);}
 private function audienceIds(string $audience): array {
  $q=User::where('is_admin',false);
  if($audience==='trial') $q->whereHas('households.subscription',fn($s)=>$s->where('status','trial'));
  elseif($audience==='paid') $q->whereHas('households.subscription',fn($s)=>$s->whereIn('status',['active','grace_period','billing_retry']));
  elseif($audience==='free') $q->whereHas('households',function($h){$h->where(function($x){$x->whereDoesntHave('subscription')->orWhereHas('subscription',fn($s)=>$s->whereNotIn('status',['active','trial','grace_period','billing_retry']));});});
  return $q->pluck('id')->all();
 }
}
