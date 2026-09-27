<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Time-limited app access ends on its date even though refresh tokens outlive the access token.
Schedule::command('apps:revoke-expired')->hourly();

// A request left pending past its SLA gets its approvers one reminder each.
Schedule::command('approvals:check-sla')->hourly();
