<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('materials:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('uploads:prune')->daily();
