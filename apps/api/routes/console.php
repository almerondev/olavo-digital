<?php

use App\Modules\Communication\Services\AnnouncementBoard;
use Illuminate\Support\Facades\Schedule;

Schedule::command('materials:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('uploads:prune')->daily();
Schedule::call(fn () => app(AnnouncementBoard::class)->notifyDue())->name('announcements:notify')->everyMinute()->withoutOverlapping();
