<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('jwt:purge-expired-revocations')->dailyAt('02:00')->withoutOverlapping();
