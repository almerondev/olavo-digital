<?php

use App\Modules\Communication\Services\AnnouncementBoard;
use App\Support\Audit\ActivityLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('materials:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('uploads:prune')->daily();
Schedule::call(fn () => ActivityLog::where('created_at', '<', now()->subDays(config('olavo.audit_retention_days')))->delete())->name('audit:prune')->daily();
Schedule::call(fn () => app(AnnouncementBoard::class)->notifyDue())->name('announcements:notify')->everyMinute()->withoutOverlapping();
