<?php
namespace App\Console\Commands;
use Illuminate\Console\Command; use App\Models\AdminAnnouncement; use App\Http\Controllers\Admin\AnnouncementController;
class SendScheduledAnnouncements extends Command { protected $signature='announcements:send-scheduled'; protected $description='Send due HouseholdOS admin announcements'; public function handle(): int {AdminAnnouncement::where('status','scheduled')->where('scheduled_at','<=',now())->orderBy('id')->chunkById(50,function($rows){foreach($rows as $a) app(AnnouncementController::class)->deliver($a);});return self::SUCCESS;} }
